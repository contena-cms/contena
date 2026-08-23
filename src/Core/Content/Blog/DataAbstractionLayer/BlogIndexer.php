<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Blog\Events\InvalidateBlogCache;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Profiling\Profiler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BlogIndexer extends EntityIndexer
{
    final public const string MANY_TO_MANY_ID_FIELD_UPDATER = 'blog.many-to-many-id-field';
    final public const string CATEGORY_DENORMALIZER_UPDATER = 'blog.category-denormalizer';
    final public const string SEARCH_KEYWORD_UPDATER = 'blog.search-keyword';

    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly Connection $connection,
        private readonly BlogCategoryDenormalizer $categoryDenormalizer,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        private readonly SearchKeywordUpdater $searchKeywordUpdater,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

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

        return new BlogIndexingMessage(array_values($ids), null, $event->getContext());
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

        $ids = $ids
                |> array_filter(...)
                |> array_unique(...)
                |> array_values(...);
        if ($ids === []) {
            return;
        }

        $context = $message->getContext();

        if ($message->allow(self::CATEGORY_DENORMALIZER_UPDATER)) {
            Profiler::trace('blog:indexer:category', function () use ($ids, $context): void {
                $this->categoryDenormalizer->update($ids, $context);
            });
        }

        if ($message->allow(self::MANY_TO_MANY_ID_FIELD_UPDATER)) {
            Profiler::trace('blog:indexer:many-to-many', function () use ($ids, $context): void {
                $this->manyToManyIdFieldUpdater->update(BlogDefinition::ENTITY_NAME, $ids, $context);
            });
        }

        if ($message->allow(self::SEARCH_KEYWORD_UPDATER)) {
            Profiler::trace('blog:indexer:search-keywords', function () use ($ids, $context): void {
                $this->searchKeywordUpdater->update($ids, $context);
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

        $this->eventDispatcher->dispatch(new InvalidateBlogCache($ids));
    }

    public function getOptions(): array
    {
        return [
            self::MANY_TO_MANY_ID_FIELD_UPDATER,
            self::CATEGORY_DENORMALIZER_UPDATER,
            self::SEARCH_KEYWORD_UPDATER,
        ];
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    private function getIterator(?array $offset): IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
    }
}
