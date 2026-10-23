<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Media\Core\Application\MediaPathUpdater;
use Contena\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\SynchronousPostUpdateIndexer;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;

class MediaPathPostUpdater extends SynchronousPostUpdateIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly MediaPathUpdater $updater,
        private readonly Connection $connection,
        private readonly EntityIndexerRegistry $indexerRegistry
    ) {
    }

    public function getName(): string
    {
        return 'media.path.post_update';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator('media', $offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $mediaWithMissingPaths = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media WHERE path IS NULL AND id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
        $this->updater->updateMedia($mediaWithMissingPaths);

        $thumbnailsWithMissingPaths = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM media_thumbnail WHERE path IS NULL AND media_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->updater->updateThumbnails($thumbnailsWithMissingPaths);

        // Because the thumbnails are changed we need to trigger the media indexer as well,
        // because the thumbnail struct is denormalized into the media table
        $mediaMessage = new MediaIndexingMessage($message->getData(), $message->getOffset(), $message->getContext());
        $mediaMessage->setIndexer('media.indexer');
        $this->indexerRegistry->__invoke($mediaMessage);
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator('media', null)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(self::class);
    }
}
