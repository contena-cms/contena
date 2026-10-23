<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterDefinition;
use Contena\Core\Content\ContentStream\DataAbstractionLayer\ContentStreamWriteResultHelper;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Symfony\Component\Messenger\MessageBusInterface;
use Contena\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class ContentStreamUpdater extends AbstractContentStreamUpdater
{
    public const INDEXER_NAME = 'content_stream_mapping.indexer';

    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $repository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly BlogDefinition $blogDefinition,
        private readonly EntityRepository $repository,
        private readonly MessageBusInterface $messageBus,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        private readonly EntityRepository $languageRepository,
        private readonly bool $indexingEnabled,
    ) {
    }

    public function getName(): string
    {
        return self::INDEXER_NAME;
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        // in full index, the blog indexer will call the `updateBlogs` method
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        if (!$message instanceof ContentStreamMappingIndexingMessage) {
            return;
        }

        $streamId = $message->getData();
        if (!\is_string($streamId)) {
            return;
        }

        /** @var array{invalid: int|string, api_filter: string|null}|false $stream */
        $stream = $this->connection->fetchAssociative(
            'SELECT invalid, api_filter FROM content_stream WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($streamId)]
        );
        // the stream is gone, its mappings went with it through the foreign key
        if ($stream === false) {
            return;
        }

        // an invalid stream, or one left without filters, has nothing to match against
        $criteria = null;
        if ((int) $stream['invalid'] === 0 && $stream['api_filter'] !== null) {
            $filter = json_decode((string) $stream['api_filter'], true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($filter)) {
                $criteria = $this->getCriteria($filter);
            }
        }

        $criteria?->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $binaryStreamId = Uuid::fromHexToBytes($streamId);

        /** @var list<string> $oldMatches */
        $oldMatches = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(blog_id)) FROM content_stream_mapping WHERE content_stream_id = :id',
            ['id' => $binaryStreamId],
        );

        if ($criteria === null) {
            // nothing left to match against, so every mapping the stream still holds has to go
            $newMatches = [];
        } else {
            try {
                $newMatches = $this->collectMatchingIdsInLanguageContexts($this->getLanguageContexts($message->getContext()), $criteria);
            } catch (UnmappedFieldException|DeprecatedUnmappedFieldException) {
                // @deprecated tag:v6.8.0 - drop DeprecatedUnmappedFieldException, unmappedField() only returns UnmappedFieldException then
                // invalid filter, remove all mappings
                $newMatches = [];
            }
        }

        $toBeAdded = array_values(array_diff($newMatches, $oldMatches));
        $toBeDeleted = array_values(array_diff($oldMatches, $newMatches));

        if ($toBeAdded !== []) {
            RetryableTransaction::retryable($this->connection, function () use ($toBeAdded, $binaryStreamId): void {
                $this->insertMappings($toBeAdded, $binaryStreamId);
            });
        }

        if ($toBeDeleted !== []) {
            RetryableTransaction::retryable($this->connection, function () use ($toBeDeleted, $binaryStreamId): void {
                $this->connection->executeStatement(
                    'DELETE FROM content_stream_mapping WHERE blog_id IN (:ids) AND content_stream_id = :streamId',
                    [
                        'ids' => Uuid::fromHexToBytesList($toBeDeleted),
                        'streamId' => $binaryStreamId,
                    ],
                    ['ids' => ArrayParameterType::BINARY],
                );
            });
        }

        $ids = array_unique([...$toBeAdded, ...$toBeDeleted]);

        foreach (array_chunk($ids, 250) as $chunkedIds) {
            $this->manyToManyIdFieldUpdater->update(
                BlogDefinition::ENTITY_NAME,
                $chunkedIds,
                $message->getContext(),
                'streamIds'
            );
        }
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        if (!$this->indexingEnabled) {
            return null;
        }

        if ($event->getEventByEntityName(ContentStreamFilterDefinition::ENTITY_NAME) === null) {
            return null;
        }

        $ids = ContentStreamWriteResultHelper::getAffectedStreamIds($event);

        if ($ids === []) {
            return null;
        }

        foreach ($ids as $id) {
            $message = new ContentStreamMappingIndexingMessage($id);
            $message->setIndexer($this->getName());
            $this->messageBus->dispatch($message);
        }

        return null;
    }

    /**
     * @param string[] $ids
     */
    public function updateBlogs(array $ids, Context $context): void
    {
        if (!$this->indexingEnabled) {
            return;
        }

        $streams = $this->connection->fetchAllAssociative('SELECT id, api_filter FROM content_stream WHERE invalid = 0 AND api_filter IS NOT NULL');

        $languageContexts = $this->getLanguageContexts($context);

        /** @var list<array{streamId: string, blogIds: list<string>}> $matches */
        $matches = [];

        foreach ($streams as $stream) {
            $filter = json_decode((string) $stream['api_filter'], true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($filter) || $filter === []) {
                continue;
            }

            $criteria = $this->getCriteria($filter, $ids);

            if ($criteria === null) {
                continue;
            }

            try {
                $matchedIds = $this->collectMatchingIdsInLanguageContexts($languageContexts, $criteria);
            } catch (UnmappedFieldException|DeprecatedUnmappedFieldException) {
                // @deprecated tag:v6.8.0 - drop DeprecatedUnmappedFieldException, unmappedField() only returns UnmappedFieldException then
                // skip if filter field is not found
                continue;
            }

            if ($matchedIds === []) {
                continue;
            }

            $matches[] = ['streamId' => (string) $stream['id'], 'blogIds' => $matchedIds];
        }

        RetryableTransaction::retryable($this->connection, function () use ($ids, $matches): void {
            if ($matches !== []) {
                $this->lockBlogs($ids);
            }

            $this->connection->executeStatement(
                'DELETE FROM content_stream_mapping WHERE blog_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::BINARY]
            );

            foreach ($matches as $match) {
                $this->insertMappings($match['blogIds'], $match['streamId']);
            }
        });
    }

    public function getTotal(): int
    {
        // full index will be done over blog indexer
        return 0;
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * Locks the blogs before the mapping rows are touched, as a concurrent blog delete takes the same
     * locks in that order through its cascade. Sorted ids keep parallel runs in one order.
     *
     * @param string[] $blogIds
     */
    private function lockBlogs(array $blogIds): void
    {
        $ids = Uuid::fromHexToBytesList($blogIds);
        sort($ids);

        foreach (array_chunk($ids, 250) as $chunk) {
            $this->connection->executeStatement(
                'SELECT id FROM blog WHERE id IN (:ids) AND version_id = :version ORDER BY id FOR UPDATE',
                [
                    'ids' => $chunk,
                    'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }

    /**
     * Selecting from `blog` keeps the existence check in the insert, as the ids come from a search that
     * ran before the write, so a blog may be deleted by now.
     *
     * @param list<string> $blogIds
     */
    private function insertMappings(array $blogIds, string $binaryStreamId): void
    {
        foreach (array_chunk($blogIds, 250) as $chunk) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO content_stream_mapping (blog_id, blog_version_id, content_stream_id)
                 SELECT blog.id, blog.version_id, :streamId
                 FROM blog
                 WHERE blog.id IN (:ids) AND blog.version_id = :version',
                [
                    'streamId' => $binaryStreamId,
                    'ids' => Uuid::fromHexToBytesList($chunk),
                    'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }

    /**
     * @return list<Context>
     */
    private function getLanguageContexts(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('channels.id', null));
        $languages = $this->languageRepository->search($criteria, Context::createDefaultContext())->getEntities();

        return array_values($languages->map(
            fn (LanguageEntity $language): Context => $this->createLanguageContext($context, $language)
        ));
    }

    private function createLanguageContext(Context $context, LanguageEntity $language): Context
    {
        $languageContext = clone $context;
        $languageContext->assign([
            'languageIdChain' => array_values(array_unique(array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM]))),
        ]);

        return $languageContext;
    }

    /**
     * @param list<Context> $languageContexts
     *
     * @return list<string>
     */
    private function collectMatchingIdsInLanguageContexts(array $languageContexts, Criteria $criteria): array
    {
        /** @var array<string, true> $matches */
        $matches = [];

        foreach ($languageContexts as $languageContext) {
            $languageMatches = $languageContext->enableInheritance(
                fn (Context $context): array => $this->repository->searchIds($criteria, $context)->getIds()
            );

            foreach ($languageMatches as $id) {
                $matches[$id] = true;
            }
        }

        return array_keys($matches);
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     * @param string[]|null $ids
     */
    private function getCriteria(array $filters, ?array $ids = null): ?Criteria
    {
        $exception = new SearchRequestException();

        $filters = $this->replaceCheapestPriceFilters($filters);
        $parsed = [];
        foreach ($filters as $filter) {
            $parsed[] = QueryStringParser::fromArray($this->blogDefinition, $filter, $exception, '');
        }

        if ($filters === []) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(...$parsed);

        if ($ids !== null) {
            $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        }

        return $criteria;
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function replaceCheapestPriceFilters(array $filters): array
    {
        foreach ($filters as $key => $filter) {
            $queries = $filter['queries'] ?? null;
            if (\is_array($queries) && $queries !== []) {
                /** @var non-empty-array<int, array<string, mixed>> $queries */
                $filters[$key]['queries'] = $this->replaceCheapestPriceFilters($queries);
            }

            if (!$priceQueries = $this->getPriceQueries($filter)) {
                continue;
            }

            $filters[$key] = [
                'type' => 'multi',
                'operator' => 'OR',
                'queries' => $priceQueries,
            ];
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function getPriceQueries(array $filter): ?array
    {
        if (!\array_key_exists('field', $filter)) {
            return null;
        }

        $fieldName = $filter['field'];

        $prefix = $this->blogDefinition->getEntityName() . '.';
        if (str_starts_with((string) $fieldName, $prefix)) {
            $fieldName = substr((string) $fieldName, \strlen($prefix));
        }

        $accessors = explode('.', (string) $fieldName);
        if (($accessors[0] ?? '') !== 'cheapestPrice') {
            return null;
        }

        $accessors[0] = '';
        $accessors = implode('.', $accessors);

        return [
            [...$filter, ...['field' => $prefix . 'price' . $accessors]],
            [...$filter, ...['field' => $prefix . 'prices.price' . $accessors]],
        ];
    }
}
