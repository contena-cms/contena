<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Core\Application\RemoteThumbnailLoader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TenantOwnedMediaAggregateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('Media aggregate tenant A')->id;
        $this->tenantB = $this->createTenant('Media aggregate tenant B')->id;
        $this->contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($this->tenantA),
            'tenant-b' => Context::createTenantContext($this->tenantB),
            'global' => Context::createGlobalContext(),
        ];
    }

    public function testReadAndWriteMatrix(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createMediaAggregate($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];
        foreach ($this->contexts as $scope => $context) {
            foreach ([
                'media_default_folder' => 'defaultFolder',
                'media_folder_configuration' => 'configuration',
                'media_thumbnail_size' => 'thumbnailSize',
                'media_folder' => 'folder',
                'media' => 'media',
                'media_thumbnail' => 'thumbnail',
                'tag' => 'tag',
            ] as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $this->assertMappingCount('media_translation', 'mediaId', array_column($ids, 'media'), $expectedCounts[$scope], $context, $scope);
            $this->assertMappingCount('media_tag', 'mediaId', array_column($ids, 'media'), $expectedCounts[$scope], $context, $scope);
            $this->assertMappingCount(
                'media_folder_configuration_media_thumbnail_size',
                'mediaFolderConfigurationId',
                array_column($ids, 'configuration'),
                $expectedCounts[$scope],
                $context,
                $scope,
            );
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            $expectedTenantId = $expectedTenants[$scope];
            $this->assertStoredTenant('media_default_folder', 'id', $scopeIds['defaultFolder'], $expectedTenantId);
            $this->assertStoredTenant('media_folder_configuration', 'id', $scopeIds['configuration'], $expectedTenantId);
            $this->assertStoredTenant('media_thumbnail_size', 'id', $scopeIds['thumbnailSize'], $expectedTenantId);
            $this->assertStoredTenant('media_folder', 'id', $scopeIds['folder'], $expectedTenantId);
            $this->assertStoredTenant('media', 'id', $scopeIds['media'], $expectedTenantId);
            $this->assertStoredTenant('media_translation', 'media_id', $scopeIds['media'], $expectedTenantId);
            $this->assertStoredTenant('media_thumbnail', 'id', $scopeIds['thumbnail'], $expectedTenantId);
            $this->assertStoredTenant('media_tag', 'media_id', $scopeIds['media'], $expectedTenantId);
            $this->assertStoredTenant(
                'media_folder_configuration_media_thumbnail_size',
                'media_folder_configuration_id',
                $scopeIds['configuration'],
                $expectedTenantId,
            );
        }

        $tenantAIds = $ids['tenant-a'];
        $this->repository('media_default_folder')->update([[
            'id' => $tenantAIds['defaultFolder'],
            'entity' => 'tenant-a-updated-' . Uuid::randomHex(),
        ]], $this->contexts['tenant-a']);
        $this->repository('media_folder_configuration')->update([[
            'id' => $tenantAIds['configuration'],
            'thumbnailQuality' => 75,
        ]], $this->contexts['tenant-a']);
        $this->repository('media_thumbnail_size')->update([[
            'id' => $tenantAIds['thumbnailSize'],
            'width' => 901,
            'height' => 901,
        ]], $this->contexts['tenant-a']);
        $this->repository('media_folder')->update([[
            'id' => $tenantAIds['folder'],
            'name' => 'Updated tenant A folder',
        ]], $this->contexts['tenant-a']);
        $this->repository('media')->update([[
            'id' => $tenantAIds['media'],
            'fileName' => 'updated-tenant-a-media',
        ]], $this->contexts['tenant-a']);
        $this->repository('media_thumbnail')->update([[
            'id' => $tenantAIds['thumbnail'],
            'path' => 'tenant-a/updated-thumbnail.png',
        ]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            foreach ([
                ['media_default_folder', ['id' => $tenantAIds['defaultFolder'], 'entity' => 'rejected-' . $scope]],
                ['media_folder_configuration', ['id' => $tenantAIds['configuration'], 'thumbnailQuality' => 60]],
                ['media_thumbnail_size', ['id' => $tenantAIds['thumbnailSize'], 'width' => 902]],
                ['media_folder', ['id' => $tenantAIds['folder'], 'name' => 'Rejected folder update']],
                ['media', ['id' => $tenantAIds['media'], 'fileName' => 'rejected-media-update']],
                ['media_thumbnail', ['id' => $tenantAIds['thumbnail'], 'path' => 'rejected-thumbnail.png']],
            ] as [$entityName, $payload]) {
                $this->assertWriteRejected(
                    fn () => $this->repository($entityName)->update([$payload], $this->contexts[$scope]),
                    'Expected ' . $entityName . ' write protection for ' . $scope,
                );
            }

            $this->assertWriteRejected(
                fn () => $this->repository('media_tag')->delete([[
                    'mediaId' => $tenantAIds['media'],
                    'tagId' => $tenantAIds['tag'],
                ]], $this->contexts[$scope]),
                'Expected media_tag write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('media_folder_configuration_media_thumbnail_size')->delete([[
                    'mediaFolderConfigurationId' => $tenantAIds['configuration'],
                    'mediaThumbnailSizeId' => $tenantAIds['thumbnailSize'],
                ]], $this->contexts[$scope]),
                'Expected media folder thumbnail size write protection for ' . $scope,
            );
        }

        $this->assertCrossTenantReferencesAreRejected($ids);
    }

    public function testRemoteThumbnailConfigurationUsesTheOriginatingContext(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createMediaAggregate('remote-' . $scope, $context);
        }

        $loader = static::getContainer()->get(RemoteThumbnailLoader::class);
        static::assertInstanceOf(RemoteThumbnailLoader::class, $loader);
        $loader->reset();

        static::assertCount(1, $this->loadRemoteThumbnails($loader, $ids['platform']['folder'], $this->contexts['platform']));
        static::assertCount(0, $this->loadRemoteThumbnails($loader, $ids['tenant-a']['folder'], $this->contexts['platform']));
        static::assertCount(1, $this->loadRemoteThumbnails($loader, $ids['tenant-a']['folder'], $this->contexts['tenant-a']));
        static::assertCount(0, $this->loadRemoteThumbnails($loader, $ids['tenant-b']['folder'], $this->contexts['tenant-a']));
        static::assertCount(1, $this->loadRemoteThumbnails($loader, $ids['tenant-b']['folder'], $this->contexts['tenant-b']));

        foreach ($ids as $scope => $scopeIds) {
            static::assertCount(
                1,
                $this->loadRemoteThumbnails($loader, $scopeIds['folder'], $this->contexts['global']),
                'Global context did not load remote thumbnails for ' . $scope,
            );
        }
    }

    public function testMediaBusinessKeysAreUniqueWithinEachOwner(): void
    {
        $entity = 'shared-media-entity-' . Uuid::randomHex();
        $dimensions = 1000 + random_int(1, 1000000);

        foreach (['platform', 'tenant-a', 'tenant-b'] as $scope) {
            $this->repository('media_default_folder')->create([[
                'id' => Uuid::randomHex(),
                'entity' => $entity,
            ]], $this->contexts[$scope]);
            $this->repository('media_thumbnail_size')->create([[
                'id' => Uuid::randomHex(),
                'width' => $dimensions,
                'height' => $dimensions,
            ]], $this->contexts[$scope]);
        }

        foreach (['platform', 'tenant-a', 'tenant-b', 'global'] as $scope) {
            $this->assertUniqueConstraintRejected(
                fn () => $this->repository('media_default_folder')->create([[
                    'id' => Uuid::randomHex(),
                    'entity' => $entity,
                ]], $this->contexts[$scope]),
                'Expected media default folder entity uniqueness for ' . $scope,
            );
            $this->assertUniqueConstraintRejected(
                fn () => $this->repository('media_thumbnail_size')->create([[
                    'id' => Uuid::randomHex(),
                    'width' => $dimensions,
                    'height' => $dimensions,
                ]], $this->contexts[$scope]),
                'Expected media thumbnail dimension uniqueness for ' . $scope,
            );
        }
    }

    /**
     * @return array{defaultFolder: string, configuration: string, thumbnailSize: string, folder: string, media: string, thumbnail: string, tag: string}
     */
    private function createMediaAggregate(string $scope, Context $context): array
    {
        $defaultFolderId = Uuid::randomHex();
        $configurationId = Uuid::randomHex();
        $thumbnailSizeId = Uuid::randomHex();
        $folderId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $thumbnailId = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        $dimensions = 500 + random_int(1, 1000000);

        $this->repository('media_default_folder')->create([[
            'id' => $defaultFolderId,
            'entity' => 'media-aggregate-' . $scope . '-' . Uuid::randomHex(),
        ]], $context);
        $this->repository('media_folder_configuration')->create([[
            'id' => $configurationId,
            'createThumbnails' => true,
        ]], $context);
        $this->repository('media_thumbnail_size')->create([[
            'id' => $thumbnailSizeId,
            'width' => $dimensions,
            'height' => $dimensions,
        ]], $context);
        $this->repository('media_folder_configuration_media_thumbnail_size')->create([[
            'mediaFolderConfigurationId' => $configurationId,
            'mediaThumbnailSizeId' => $thumbnailSizeId,
        ]], $context);
        $this->repository('media_folder')->create([[
            'id' => $folderId,
            'defaultFolderId' => $defaultFolderId,
            'configurationId' => $configurationId,
            'name' => 'Media aggregate folder ' . $scope,
            'useParentConfiguration' => false,
        ]], $context);
        $this->repository('media')->create([[
            'id' => $mediaId,
            'mediaFolderId' => $folderId,
            'fileName' => 'media-aggregate-' . $scope,
            'title' => 'Media aggregate ' . $scope,
        ]], $context);
        $this->repository('tag')->create([[
            'id' => $tagId,
            'name' => 'Media aggregate tag ' . $scope . '-' . Uuid::randomHex(),
        ]], $context);
        $this->repository('media_tag')->create([[
            'mediaId' => $mediaId,
            'tagId' => $tagId,
        ]], $context);
        $this->repository('media_thumbnail')->create([[
            'id' => $thumbnailId,
            'mediaId' => $mediaId,
            'mediaThumbnailSizeId' => $thumbnailSizeId,
            'width' => $dimensions,
            'height' => $dimensions,
            'path' => 'media-aggregate/' . $thumbnailId . '.png',
        ]], $context);

        return [
            'defaultFolder' => $defaultFolderId,
            'configuration' => $configurationId,
            'thumbnailSize' => $thumbnailSizeId,
            'folder' => $folderId,
            'media' => $mediaId,
            'thumbnail' => $thumbnailId,
            'tag' => $tagId,
        ];
    }

    /**
     * @param array<string, array{defaultFolder: string, configuration: string, thumbnailSize: string, folder: string, media: string, thumbnail: string, tag: string}> $ids
     */
    private function assertCrossTenantReferencesAreRejected(array $ids): void
    {
        $this->assertWriteRejected(
            fn () => $this->repository('media_tag')->create([[
                'mediaId' => $ids['tenant-a']['media'],
                'tagId' => $ids['tenant-b']['tag'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant media tag referencing another tenant tag to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('media_folder_configuration_media_thumbnail_size')->create([[
                'mediaFolderConfigurationId' => $ids['tenant-a']['configuration'],
                'mediaThumbnailSizeId' => $ids['platform']['thumbnailSize'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant media configuration referencing a platform thumbnail size to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('media_folder')->create([[
                'id' => Uuid::randomHex(),
                'configurationId' => $ids['tenant-b']['configuration'],
                'name' => 'Invalid cross-tenant folder',
            ]], $this->contexts['tenant-a']),
            'Expected a tenant media folder referencing another tenant configuration to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('media_thumbnail')->create([[
                'id' => Uuid::randomHex(),
                'mediaId' => $ids['tenant-a']['media'],
                'mediaThumbnailSizeId' => $ids['tenant-b']['thumbnailSize'],
                'width' => 100,
                'height' => 100,
            ]], $this->contexts['tenant-a']),
            'Expected a tenant media thumbnail referencing another tenant size to be rejected',
        );
    }

    /**
     * @return EntityCollection<Entity>
     */
    private function loadRemoteThumbnails(RemoteThumbnailLoader $loader, string $folderId, Context $context): EntityCollection
    {
        $media = new PartialEntity();
        $media->assign([
            'id' => Uuid::randomHex(),
            'path' => 'media-aggregate/remote.png',
            'mediaFolderId' => $folderId,
            'private' => false,
        ]);

        $loader->load([$media], $context);
        $thumbnails = $media->get('thumbnails');
        static::assertInstanceOf(EntityCollection::class, $thumbnails);

        return $thumbnails;
    }

    /**
     * @param list<string> $ids
     */
    private function assertMappingCount(string $entityName, string $property, array $ids, int $expected, Context $context, string $scope): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter($property, $ids));

        static::assertCount(
            $expected,
            $this->repository($entityName)->searchIds($criteria, $context)->getIds(),
            'Unexpected ' . $entityName . ' rows for ' . $scope,
        );
    }

    private function assertStoredTenant(string $table, string $idColumn, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `%s` = :id', $table, $idColumn),
            ['id' => Uuid::fromHexToBytes($id)],
        );

        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
    }

    private function assertWriteRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (WriteException) {
        }
    }

    private function assertUniqueConstraintRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (UniqueConstraintViolationException) {
        }
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
