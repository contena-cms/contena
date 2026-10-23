<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Blog\Events\InvalidateBlogCache;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Profiling\Profiler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class BlogIndexer extends EntityIndexer
{
    final public const INHERITANCE_UPDATER = 'blog.inheritance';
    final public const STOCK_UPDATER = 'blog.stock';
    final public const VARIANT_LISTING_UPDATER = 'blog.variant-listing';
    final public const CHILD_COUNT_UPDATER = 'blog.child-count';
    final public const MANY_TO_MANY_ID_FIELD_UPDATER = 'blog.many-to-many-id-field';
    final public const CATEGORY_DENORMALIZER_UPDATER = 'blog.category-denormalizer';
    final public const CHEAPEST_PRICE_UPDATER = 'blog.cheapest-price';
    final public const RATING_AVERAGE_UPDATER = 'blog.rating-average';
    final public const STREAM_UPDATER = 'blog.stream';
    final public const SEARCH_KEYWORD_UPDATER = 'blog.search-keyword';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as blog states are deprecated.
     */
    final public const STATES_UPDATER = 'blog.states';
    private const UPDATE_IDS_CHUNK_SIZE = 50;


    public function getName(): string
    {
        return 'blog.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->getIterator($offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new BlogIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $ids = $event->getPrimaryKeys(BlogDefinition::ENTITY_NAME);

        if ($ids === []) {
            return null;
        }

        $childrenIds = $this->getChildrenIds($ids);

        $parentAndChildIdsToBeChunked = \array_diff(\array_unique(\array_filter(\array_merge(
            $this->getParentIds($ids),
            $childrenIds
        ))), $ids);

        if (\count($parentAndChildIdsToBeChunked) + \count($ids) < self::UPDATE_IDS_CHUNK_SIZE) {
            $ids = array_unique(array_merge($ids, $parentAndChildIdsToBeChunked));
            $parentAndChildIdsToBeChunked = [];
        }

        Profiler::trace('blog:indexer:inheritance', function () use ($ids, $event): void {
            $this->inheritanceUpdater->update(BlogDefinition::ENTITY_NAME, $ids, $event->getContext());
        });

        $stocks = $event->getPrimaryKeysWithPropertyChange(BlogDefinition::ENTITY_NAME, ['stock', 'isCloseout', 'minPurchase']);

        // Variants inherit `isCloseout` and `minPurchase`, so a change on the parent changes their availability as well.
        // If none of the written blogs has variants, there is nothing to look up
        $inheritedStocks = $event->getPrimaryKeysWithPropertyChange(BlogDefinition::ENTITY_NAME, ['isCloseout', 'minPurchase']);
        if ($inheritedStocks !== [] && $childrenIds !== []) {
            $stocks = \array_unique([...$stocks, ...$this->getInheritingChildrenIds($inheritedStocks)]);
        }

        Profiler::trace('blog:indexer:stock', function () use ($stocks, $event): void {
            $this->stockStorage->index(array_values($stocks), $event->getContext());
        });

        foreach (\array_chunk($parentAndChildIdsToBeChunked, self::UPDATE_IDS_CHUNK_SIZE) as $chunk) {
            $child = new BlogIndexingMessage($chunk, null, $event->getContext());
            $child->setIndexer($this->getName());
            EntityIndexerRegistry::addSkips($child, $event->getContext());

            $this->messageBus->dispatch($child);
        }

        $idsToBeChunked = \array_chunk($ids, self::UPDATE_IDS_CHUNK_SIZE);
        $idsForReturnedMessage = \array_shift($idsToBeChunked);

        foreach ($idsToBeChunked as $chunk) {
            $message = new BlogIndexingMessage($chunk, null, $event->getContext());
            $message->setIndexer($this->getName());
            $message->addSkip(self::INHERITANCE_UPDATER, self::STOCK_UPDATER);
            EntityIndexerRegistry::addSkips($message, $event->getContext());

            if ($event->isCloned()) {
                $message->addSkip(self::CHILD_COUNT_UPDATER);
            }

            $this->messageBus->dispatch($message);
        }

        $message = new BlogIndexingMessage($idsForReturnedMessage, null, $event->getContext());
        $message->addSkip(self::INHERITANCE_UPDATER, self::STOCK_UPDATER);
        EntityIndexerRegistry::addSkips($message, $event->getContext());

        if ($event->isCloned()) {
            $message->addSkip(self::CHILD_COUNT_UPDATER);
        }

        return $message;
    }

    public function getTotal(): int
    {
        return $this->getIterator(null)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        $parentIds = $this->filterVariants($ids);

        $context = $message->getContext();

        if ($message->allow(self::INHERITANCE_UPDATER)) {
            Profiler::trace('blog:indexer:inheritance', function () use ($ids, $context): void {
                $this->inheritanceUpdater->update(BlogDefinition::ENTITY_NAME, $ids, $context);
            });
        }

        if ($message->allow(self::STOCK_UPDATER)) {
            Profiler::trace('blog:indexer:stock', function () use ($ids, $context): void {
                $this->stockStorage->index($ids, $context);
            });
        }

        if ($message->allow(self::VARIANT_LISTING_UPDATER)) {
            Profiler::trace('blog:indexer:variant-listing', function () use ($parentIds, $context): void {
                $this->variantListingUpdater->update($parentIds, $context);
            });
        }

        if ($message->allow(self::CHILD_COUNT_UPDATER)) {
            Profiler::trace('blog:indexer:child-count', function () use ($parentIds, $context): void {
                $this->childCountUpdater->update(BlogDefinition::ENTITY_NAME, $parentIds, $context);
            });
        }

        if ($message->allow(self::CATEGORY_DENORMALIZER_UPDATER)) {
            Profiler::trace('blog:indexer:category', function () use ($ids, $context): void {
                $this->categoryDenormalizer->update($ids, $context);
            });
        }

        if ($message->allow(self::CHEAPEST_PRICE_UPDATER)) {
            Profiler::trace('blog:indexer:cheapest-price', function () use ($parentIds, $context): void {
                $this->cheapestPriceUpdater->update($parentIds, $context);
            });
        }

        if ($message->allow(self::RATING_AVERAGE_UPDATER)) {
            Profiler::trace('blog:indexer:rating', function () use ($ids, $parentIds, $context): void {
                $this->ratingAverageUpdater->update(array_unique([...$parentIds, ...$this->getParentIds($ids)]), $context);
            });
        }

        if ($message->allow(self::SEARCH_KEYWORD_UPDATER)) {
            Profiler::trace('blog:indexer:search-keywords', function () use ($ids, $context): void {
                $this->searchKeywordUpdater->update($ids, $context);
            });
        }

        if (!Feature::isActive('v6.8.0.0') && $message->allow(self::STATES_UPDATER)) {
            Profiler::trace('blog:indexer:states', function () use ($ids, $context): void {
                $this->statesUpdater?->update($ids, $context);
            });
        }

        // STREAM_UPDATER should be ran after other fields updater like categoriesRo or cheapestPriceUpdater so it could use the correct latest value
        if ($message->allow(self::STREAM_UPDATER)) {
            Profiler::trace('blog:indexer:streams', function () use ($ids, $context): void {
                $this->streamUpdater->updateBlogs($ids, $context);
            });
        }

        // manyToManyIdFieldUpdater should be run last so it can get the correct streamIds, categoryIds etc
        if ($message->allow(self::MANY_TO_MANY_ID_FIELD_UPDATER)) {
            Profiler::trace('blog:indexer:many-to-many', function () use ($ids, $context): void {
                $this->manyToManyIdFieldUpdater->update(BlogDefinition::ENTITY_NAME, $ids, $context);
            });
        }

        RetryableQuery::retryable($this->connection, function () use ($ids): void {
            $this->connection->executeStatement(
                'UPDATE blog SET updated_at = :now WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids), 'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        Profiler::trace('blog:indexer:event', function () use ($ids, $context, $message): void {
            $this->eventDispatcher->dispatch(new BlogIndexerEvent($ids, $context, $message->getSkip()));
        });

        $this->eventDispatcher->dispatch(new InvalidateBlogCache($ids, false));
    }

    public function getOptions(): array
    {
        return [
            self::INHERITANCE_UPDATER,
            self::STOCK_UPDATER,
            self::VARIANT_LISTING_UPDATER,
            self::CHILD_COUNT_UPDATER,
            self::MANY_TO_MANY_ID_FIELD_UPDATER,
            self::CATEGORY_DENORMALIZER_UPDATER,
            self::CHEAPEST_PRICE_UPDATER,
            self::RATING_AVERAGE_UPDATER,
            self::STREAM_UPDATER,
            self::SEARCH_KEYWORD_UPDATER,
        ];
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getChildrenIds(array $ids): array
    {
        $childrenIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id)) as id FROM blog WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return array_unique(array_filter($childrenIds));
    }

    /**
     * Variants of the given blogs that inherit `isCloseout` or `minPurchase`
     *
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getInheritingChildrenIds(array $ids): array
    {
        $childrenIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id)) as id FROM blog WHERE parent_id IN (:ids) AND (is_closeout IS NULL OR min_purchase IS NULL)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return array_unique(array_filter($childrenIds));
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getParentIds(array $ids): array
    {
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(blog.parent_id)) as id FROM blog WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return array_unique(array_filter($parentIds));
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function filterVariants(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(`id`))
             FROM blog
             WHERE `id` IN (:ids)
             AND `parent_id` IS NULL',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    private function getIterator(?array $offset): IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
    }
}
