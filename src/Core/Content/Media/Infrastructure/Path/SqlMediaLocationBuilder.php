<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Contena\Core\Content\Media\Core\Event\MediaLocationEvent;
use Contena\Core\Content\Media\Core\Event\ThumbnailLocationEvent;
use Contena\Core\Content\Media\Core\Params\MediaLocationStruct;
use Contena\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Content\Media\Infrastructure\Path\MediaLocationBuilderTest
 */
class SqlMediaLocationBuilder implements MediaLocationBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Connection $connection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function media(array $ids): array
    {
        $ids = \array_unique($ids);
        if ($ids === []) {
            return [];
        }

        $data = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(id)) as array_key,
                    LOWER(HEX(id)) as id,
                    file_extension,
                    file_name,
                    uploaded_at,
                    LOWER(HEX(tenant_id)) as tenant_id
            FROM media
            WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $locations = [];

        foreach ($data as $key => $row) {
            $locations[(string) $key] = new MediaLocationStruct(
                $row['id'],
                $row['file_extension'],
                $row['file_name'],
                $row['uploaded_at'] ? new \DateTimeImmutable($row['uploaded_at']) : null,
                $row['tenant_id'] ?? null,
            );
        }

        $this->dispatcher->dispatch(
            new MediaLocationEvent($locations)
        );

        return $locations;
    }

    /**
     * {@inheritdoc}
     */
    public function thumbnails(array $ids): array
    {
        $ids = \array_unique($ids);
        if ($ids === []) {
            return [];
        }

        $data = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(media_thumbnail.id)) as array_key,
                    LOWER(HEX(media_thumbnail.id)) as id,
                    media.file_extension,
                    media.file_name,
                    LOWER(HEX(media.id)) as media_id,
                    LOWER(HEX(media.tenant_id)) as tenant_id,
                    width,
                    height,
                    uploaded_at
            FROM media_thumbnail
                INNER JOIN media ON media.id = media_thumbnail.media_id
            WHERE media_thumbnail.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $locations = [];

        foreach ($data as $key => $row) {
            $media = new MediaLocationStruct(
                $row['media_id'],
                $row['file_extension'],
                $row['file_name'],
                $row['uploaded_at'] ? new \DateTimeImmutable($row['uploaded_at']) : null,
                $row['tenant_id'] ?? null,
            );

            $locations[(string) $key] = new ThumbnailLocationStruct(
                $row['id'],
                (int) $row['width'],
                (int) $row['height'],
                $media
            );
        }

        $this->dispatcher->dispatch(
            new ThumbnailLocationEvent($locations)
        );

        return $locations;
    }
}
