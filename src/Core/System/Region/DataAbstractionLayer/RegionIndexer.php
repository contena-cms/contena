<?php declare(strict_types=1);

namespace Contena\Core\System\Region\DataAbstractionLayer;

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
use Contena\Core\System\Region\Event\RegionIndexerEvent;
use Contena\Core\System\Region\RegionCollection;
use Contena\Core\System\Region\RegionDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Maintains the denormalized tree fields used by administrative regions.
 *
 * @internal
 */
class RegionIndexer extends EntityIndexer
{
    final public const string CHILD_COUNT_UPDATER = 'region.child-count';

    final public const string TREE_UPDATER = 'region.tree';

    /**
     * @param EntityRepository<RegionCollection> $regionRepository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $regionRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChildCountUpdater $childCountUpdater,
        private readonly TreeUpdater $treeUpdater,
    ) {
    }

    public function getName(): string
    {
        return 'region.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->regionRepository->getDefinition(), $offset);
        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(RegionDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        $idsWithChangedParentIds = [];
        foreach ($event->getResults(RegionDefinition::ENTITY_NAME)->withPayloadProperties('parentId') as $result) {
            $idsWithChangedParentIds[] = $result->getProperty('id');
        }

        if ($idsWithChangedParentIds !== []) {
            $this->treeUpdater->batchUpdate($idsWithChangedParentIds, RegionDefinition::ENTITY_NAME, $event->getContext(), true);
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
            $this->childCountUpdater->update(RegionDefinition::ENTITY_NAME, $ids, $message->getContext());
        }

        if ($message->allow(self::TREE_UPDATER)) {
            $this->treeUpdater->batchUpdate($ids, RegionDefinition::ENTITY_NAME, $message->getContext(), !$message->isFullIndexing);
        }

        $this->eventDispatcher->dispatch(new RegionIndexerEvent($ids, $message->getContext(), array_values($message->getSkip())));
    }

    public function getOptions(): array
    {
        return [self::CHILD_COUNT_UPDATER, self::TREE_UPDATER];
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->regionRepository->getDefinition())->fetchCount();
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
            'SELECT LOWER(HEX(id)) as id FROM region WHERE parent_id IN (:ids)',
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
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(parent_id)) as id FROM region WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $parentIds
                |> array_unique(...)
                |> array_filter(...)
                |> array_values(...);
    }
}
