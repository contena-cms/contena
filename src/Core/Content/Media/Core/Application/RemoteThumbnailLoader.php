<?php
declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Application;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Contena\Core\Content\Media\Core\Params\UrlParams;
use Contena\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The remote thumbnail loader is responsible for generating the urls for media entities, and it's thumbnails.
 *
 * @final
 */
class RemoteThumbnailLoader implements ResetInterface
{
    /**
     * @var array<string, array<string, array<array{media_thumbnail_size_id: string, width: string, height: string}>>>
     */
    private array $mediaFolderThumbnailSizes = [];

    /**
     * @internal
     *
     * @param list<array{width: int, height: int}> $fallbackThumbnailSizes
     */
    public function __construct(
        private readonly AbstractMediaUrlGenerator $generator,
        private readonly Connection $connection,
        private readonly FilesystemOperator $filesystem,
        private readonly ExtensionDispatcher $extensions,
        private readonly string $pattern = '',
        private readonly array $fallbackThumbnailSizes = []
    ) {
    }

    /**
     * Collects all urls of the media entities and triggers the AbstractMediaUrlGenerator to generate the urls.
     * The generated urls will be assigned to the entities afterward.
     *
     * Generates the thumbnails for the media entities according to the provided pattern and media thumbnail sizes.
     * The generated thumbnails will be assigned to the entities afterward.
     *
     * @param iterable<MediaEntity|PartialEntity> $media
     */
    public function load(iterable $media, ?Context $context = null): void
    {
        $context ??= Context::createDefaultContext();
        $mapping = $this->map($media);

        if ($mapping === []) {
            return;
        }

        $urls = $this->generator->generate($mapping);

        $mediaThumbnailSizes = $this->getMediaThumbnailSizes($context);
        $baseUrl = $this->getBaseUrl();

        foreach ($media as $mediaEntity) {
            if (!isset($urls[$mediaEntity->getUniqueIdentifier()])) {
                continue;
            }

            $mediaEntity->assign(['url' => $urls[$mediaEntity->getUniqueIdentifier()]]);

            $mediaFolderId = $mediaEntity->get('mediaFolderId');
            $thumbnailSizes = $mediaThumbnailSizes[$mediaFolderId] ?? [];

            if ($thumbnailSizes === []) {
                $mediaEntity->assign(['thumbnails' => new MediaThumbnailCollection()]);

                continue;
            }

            $thumbnails = new MediaThumbnailCollection();
            foreach ($thumbnailSizes as $size) {
                $url = $this->getUrl(
                    $mediaEntity,
                    $baseUrl,
                    $size['width'],
                    $size['height']
                );
                if ($url === null) {
                    continue;
                }

                $thumbnail = new MediaThumbnailEntity();
                $thumbnail->assign([
                    'id' => Uuid::randomHex(),
                    'mediaId' => $mediaEntity->getUniqueIdentifier(),
                    'mediaThumbnailSizeId' => $size['media_thumbnail_size_id'],
                    'width' => (int) $size['width'],
                    'height' => (int) $size['height'],
                    'url' => $url,
                ]);

                $thumbnails->add($thumbnail);
            }

            $mediaEntity->assign(['thumbnails' => $thumbnails]);
        }
    }

    public function reset(): void
    {
        $this->mediaFolderThumbnailSizes = [];
    }

    /**
     * @param iterable<Entity> $entities
     *
     * @return array<string, UrlParams>
     */
    private function map(iterable $entities): array
    {
        $mapped = [];

        foreach ($entities as $entity) {
            if (!$entity->has('path') || (string) $entity->get('path') === '') {
                continue;
            }
            // don't generate private urls
            if (!$entity->has('private') || $entity->get('private')) {
                continue;
            }

            $mapped[$entity->getUniqueIdentifier()] = UrlParams::fromMedia($entity);
        }

        return $mapped;
    }

    /**
     * @return array<string, array<array{media_thumbnail_size_id: string, width: string, height: string}>>
     */
    private function getMediaThumbnailSizes(Context $context): array
    {
        $cacheKey = $this->getTenantCacheKey($context);
        if (isset($this->mediaFolderThumbnailSizes[$cacheKey])) {
            return $this->mediaFolderThumbnailSizes[$cacheKey];
        }

        $tenantId = $context->getTenantId();
        $parameters = [];
        $tenantCondition = '';
        if (!$context->hasGlobalTenantAccess()) {
            $tenantCondition = ' WHERE mfcmts.tenant_id IS NULL AND mts.tenant_id IS NULL';
            if ($tenantId !== null) {
                $tenantCondition = ' WHERE mfcmts.tenant_id = :tenantId AND mts.tenant_id = :tenantId';
                $parameters['tenantId'] = Uuid::fromHexToBytes($tenantId);
            }
        }

        /** @var list<array{configuration_id: string, media_thumbnail_size_id: string, width: string, height: string}> $sizes */
        $sizes = $this->connection->fetchAllAssociative(
            '
            SELECT
                LOWER(HEX(mfcmts.media_folder_configuration_id)) AS configuration_id,
                LOWER(HEX(mts.id)) AS media_thumbnail_size_id,
                mts.width,
                mts.height
            FROM media_folder_configuration_media_thumbnail_size mfcmts
            INNER JOIN media_thumbnail_size mts ON mfcmts.media_thumbnail_size_id = mts.id' . $tenantCondition,
            $parameters,
        );

        if ($sizes === [] && $this->fallbackThumbnailSizes === []) {
            return $this->mediaFolderThumbnailSizes[$cacheKey] = [];
        }

        $configurationSizes = [];
        foreach ($sizes as $size) {
            $configurationSizes[$size['configuration_id']][] = [
                'media_thumbnail_size_id' => $size['media_thumbnail_size_id'],
                'width' => $size['width'],
                'height' => $size['height'],
            ];
        }

        $folderTenantCondition = '';
        if (!$context->hasGlobalTenantAccess()) {
            $folderTenantCondition = ' AND mf.tenant_id IS NULL AND mfc.tenant_id IS NULL';
            if ($tenantId !== null) {
                $folderTenantCondition = ' AND mf.tenant_id = :tenantId AND mfc.tenant_id = :tenantId';
            }
        }

        /** @var list<array{folder_id: string, configuration_id: string, create_thumbnails: string}> $folderConfigurations */
        $folderConfigurations = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(mf.id)) AS folder_id,
                LOWER(HEX(mf.media_folder_configuration_id)) AS configuration_id,
                mfc.create_thumbnails
            FROM media_folder mf
            INNER JOIN media_folder_configuration mfc ON mf.media_folder_configuration_id = mfc.id
            WHERE mf.media_folder_configuration_id IS NOT NULL' . $folderTenantCondition,
            $parameters,
        );

        $grouped = [];
        foreach ($folderConfigurations as $folderConfiguration) {
            if (!$folderConfiguration['create_thumbnails']) {
                $grouped[$folderConfiguration['folder_id']] = [];

                continue;
            }

            $grouped[$folderConfiguration['folder_id']] = $configurationSizes[$folderConfiguration['configuration_id']] ?? \array_map(
                static fn (array $size): array => [
                    'media_thumbnail_size_id' => Uuid::fromStringToHex(\sprintf('remote-thumbnail-fallback-%dx%d', $size['width'], $size['height'])),
                    'width' => (string) $size['width'],
                    'height' => (string) $size['height'],
                ],
                $this->fallbackThumbnailSizes
            );
        }

        return $this->mediaFolderThumbnailSizes[$cacheKey] = $grouped;
    }

    private function getTenantCacheKey(Context $context): string
    {
        if ($context->hasGlobalTenantAccess()) {
            return 'global';
        }

        return $context->getTenantId() ?? 'platform';
    }

    private function getBaseUrl(): string
    {
        return \rtrim($this->filesystem->publicUrl(''), '/');
    }

    private function getUrl(MediaEntity|PartialEntity $mediaEntity, string $mediaUrl, string $width, string $height): ?string
    {
        return $this->extensions->publish(
            name: ResolveRemoteThumbnailUrlExtension::NAME,
            extension: new ResolveRemoteThumbnailUrlExtension(
                $mediaUrl,
                $width,
                $height,
                $this->pattern,
                $mediaEntity,
            ),
            function: static function (
                string $mediaUrl,
                string $width,
                string $height,
                string $pattern,
                Entity $mediaEntity,
            ): string {
                $mediaPath = $mediaEntity->get('path');
                \assert(\is_string($mediaPath));
                $mediaUpdatedAt = $mediaEntity->get('updatedAt') ?? $mediaEntity->get('createdAt');
                \assert($mediaUpdatedAt instanceof \DateTimeInterface || $mediaUpdatedAt === null);

                $replacements = [
                    str_starts_with($mediaPath, 'http') ? '' : $mediaUrl,
                    $mediaPath,
                    $width,
                    $height,
                    (string) $mediaUpdatedAt?->getTimestamp() ?: '',
                ];

                $url = str_replace(
                    ['{mediaUrl}', '{mediaPath}', '{width}', '{height}', '{mediaUpdatedAt}'],
                    $replacements,
                    $pattern
                );

                return str_starts_with($mediaPath, 'http') ? ltrim($url, '/') : $url;
            }
        );
    }
}
