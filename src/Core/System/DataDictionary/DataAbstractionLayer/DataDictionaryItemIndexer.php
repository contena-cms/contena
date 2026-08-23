<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemDefinition;
use Contena\Core\System\DataDictionary\Event\DataDictionaryItemIndexerEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Maintains the denormalized tree fields used by data dictionary values.
 *
 * @internal
 */
class DataDictionaryItemIndexer extends EntityIndexer
{
    final public const string CHILD_COUNT_UPDATER = 'data_dictionary_item.child-count';

    final public const string TREE_UPDATER = 'data_dictionary_item.tree';

    /**
     * @param EntityRepository<DataDictionaryItemCollection> $itemRepository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $itemRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChildCountUpdater $childCountUpdater,
        private readonly TreeUpdater $treeUpdater
    ) {
    }

    public function getName(): string
    {
        return 'data_dictionary_item.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->itemRepository->getDefinition(), $offset);
        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(DataDictionaryItemDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        $idsWithChangedParentIds = [];
        foreach ($event->getResults(DataDictionaryItemDefinition::ENTITY_NAME)->withPayloadProperties('parentId') as $result) {
            $idsWithChangedParentIds[] = $result->getProperty('id');
        }

        if ($idsWithChangedParentIds !== []) {
            $this->treeUpdater->batchUpdate(
                $idsWithChangedParentIds,
                DataDictionaryItemDefinition::ENTITY_NAME,
                $event->getContext(),
                true
            );
        }

        $updates = array_values(array_merge($updates, $this->fetchChildren($updates), $this->getParentIds($updates)));

        return new EntityIndexingMessage($updates, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = $ids
                |> array_unique(...)
                |> array_filter(...)
                |> array_values(...);
        if ($ids === []) {
            return;
        }

        if ($message->allow(self::CHILD_COUNT_UPDATER)) {
            $this->childCountUpdater->update(DataDictionaryItemDefinition::ENTITY_NAME, $ids, $message->getContext());
        }

        if ($message->allow(self::TREE_UPDATER)) {
            $this->treeUpdater->batchUpdate(
                $ids,
                DataDictionaryItemDefinition::ENTITY_NAME,
                $message->getContext(),
                !$message->isFullIndexing
            );
        }

        $this->eventDispatcher->dispatch(
            new DataDictionaryItemIndexerEvent($ids, $message->getContext(), array_values($message->getSkip()))
        );
    }

    public function getOptions(): array
    {
        return [
            self::CHILD_COUNT_UPDATER,
            self::TREE_UPDATER,
        ];
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->itemRepository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param array<string> $parentIds
     *
     * @return array<string>
     */
    private function fetchChildren(array $parentIds): array
    {
        $childIds = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id FROM data_dictionary_item WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($parentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $childIds = array_column($childIds, 'id');

        if ($childIds !== []) {
            $childIds = array_merge($childIds, $this->fetchChildren($childIds));
        }

        return $childIds;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getParentIds(array $ids): array
    {
        /** @var list<string> $parentIds */
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(parent_id)) as id FROM data_dictionary_item WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $parentIds
                |> array_filter(...)
                |> array_unique(...)
                |> array_values(...);
    }
}
