<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary\BlogKeywordDictionaryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchKeywordAnalyzerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-type ConfigField array{field: string, tokenize: '1'|'0', ranking: numeric-string, language_id: string}
 */
class SearchKeywordUpdater implements ResetInterface
{
    /**
     * @var array<string, array<int, ConfigField>>
     */
    private array $config = [];

    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $blogRepository,
        private readonly BlogSearchKeywordAnalyzerInterface $analyzer,
        private readonly ClockInterface $clock,
        private readonly bool $searchKeywordIndexingEnabled = true,
    ) {
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        if (!$this->searchKeywordIndexingEnabled) {
            return;
        }

        if ($ids === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new NandFilter([new EqualsFilter('channels.id', null)]));
        $languages = $this->languageRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $languages = $this->sortLanguages($languages);

        $blogs = [];
        foreach ($languages as $language) {
            $languageContext = new Context(
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                array_values(array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM])),
                $context->getVersionId()
            );

            $existingBlogs = $blogs[$language->getParentId() ?? Defaults::LANGUAGE_SYSTEM] ?? [];

            $blogs[$language->getId()] = $this->updateLanguage($ids, $languageContext, $existingBlogs);
        }
    }

    public function reset(): void
    {
        $this->config = [];
    }

    /**
     * @param array<string> $ids
     * @param BlogEntity[] $existingBlogs
     *
     * @return BlogEntity[]
     */
    private function updateLanguage(array $ids, Context $context, array $existingBlogs): array
    {
        $configFields = $this->getConfigFields($context->getLanguageId());

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());

        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->delete($ids, $context->getLanguageId(), $context->getVersionId());

        $keywords = [];
        $dictionary = [];

        $iterator = $this->getIterator($ids, $context, $configFields);

        while ($blogs = $iterator->fetch()) {
            foreach ($blogs->getEntities() as $blog) {
                // overwrite fetched blogs if translations for that blog exists
                // otherwise we use the already fetched blog from the parent language
                $existingBlogs[$blog->getId()] = $blog;
            }
        }

        $this->assignParentBlogs($existingBlogs, $configFields, $context);

        foreach ($existingBlogs as $blog) {
            $analyzed = $this->analyzer->analyze($blog, $context, $configFields);

            $blogId = Uuid::fromHexToBytes($blog->getId());

            foreach ($analyzed as $keyword) {
                $keywords[] = [
                    'id' => Uuid::randomBytes(),
                    'version_id' => $versionId,
                    'blog_version_id' => $versionId,
                    'language_id' => $languageId,
                    'blog_id' => $blogId,
                    'keyword' => $keyword->getKeyword(),
                    'ranking' => $keyword->getRanking(),
                    'created_at' => $now,
                ];
                $key = $keyword->getKeyword() . $languageId;
                $dictionary[$key] = [
                    'id' => Uuid::randomBytes(),
                    'language_id' => $languageId,
                    'keyword' => $keyword->getKeyword(),
                ];
            }
        }

        $this->insertKeywords($keywords);
        $this->insertDictionary($dictionary);

        return $existingBlogs;
    }

    /**
     * @param array<string> $ids
     * @param array<int, ConfigField> $configFields
     *
     * @return RepositoryIterator<BlogCollection>
     */
    private function getIterator(array $ids, Context $context, array $configFields): RepositoryIterator
    {
        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids);
        $criteria->setLimit(50);

        $this->buildCriteria(array_column($configFields, 'field'), $criteria, $context);

        return new RepositoryIterator($this->blogRepository, $context, $criteria);
    }

    /**
     * @param array<string> $ids
     */
    private function delete(array $ids, string $languageId, string $versionId): void
    {
        $bytes = Uuid::fromHexToBytesList($ids);

        $params = [
            'ids' => $bytes,
            'language' => Uuid::fromHexToBytes($languageId),
            'versionId' => Uuid::fromHexToBytes($versionId),
        ];

        RetryableQuery::retryable($this->connection, function () use ($params): void {
            $this->connection->executeStatement(
                'DELETE FROM blog_search_keyword WHERE blog_id IN (:ids) AND language_id = :language AND version_id = :versionId',
                $params,
                ['ids' => ArrayParameterType::BINARY]
            );
        });
    }

    /**
     * @param list<array{id: string, version_id: string, blog_version_id: string, language_id: string, blog_id: string, keyword: string, ranking: float, created_at: string}> $keywords
     */
    private function insertKeywords(array $keywords): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 50, true);
        foreach ($keywords as $insert) {
            $queue->addInsert(BlogSearchKeywordDefinition::ENTITY_NAME, $insert);
        }
        $queue->execute();
    }

    /**
     * @param array<string, array{id: string, language_id: string, keyword: string}> $dictionary
     */
    private function insertDictionary(array $dictionary): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 50, true, true);

        foreach ($dictionary as $insert) {
            $queue->addInsert(BlogKeywordDictionaryDefinition::ENTITY_NAME, $insert);
        }
        $queue->execute();
    }

    /**
     * @param list<string> $accessors
     */
    private function buildCriteria(array $accessors, Criteria $criteria, Context $context): void
    {
        $definition = $this->blogRepository->getDefinition();

        // Filter for blogs that have translations anywhere in the language inheritance chain
        // (current language, its parent and the system default). A sales channel language may
        // inherit from a parent language that is not itself indexed (e.g. de-CH inheriting de-DE),
        // in which case the carried-over keywords of the parent language are not available and the
        // blog must be fetched here so its inherited translation can be indexed.
        $filters = [
            new EqualsAnyFilter('translations.languageId', $context->getLanguageIdChain()),
            new EqualsAnyFilter('parent.translations.languageId', $context->getLanguageIdChain()),
        ];

        foreach ($accessors as $accessor) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor);

            $fields = array_filter($fields, static fn (Field $field) => $field instanceof AssociationField);

            if ($fields === []) {
                continue;
            }

            $lastAssociationField = $fields[\count($fields) - 1];

            $path = array_map(static fn (Field $field) => $field->getPropertyName(), $fields);

            $association = implode('.', $path);
            if ($association === 'parent') {
                // Blog parent associations cannot be loaded inline and must be fetched separately.
                continue;
            }

            if ($criteria->hasAssociation($association)) {
                continue;
            }

            $criteria->addAssociation($association);

            $translationField = $lastAssociationField->getReferenceDefinition()->getTranslationField();
            if (!$translationField) {
                continue;
            }

            // filter the associations that have no translations in given language,
            // as we automatically use the parent languages keywords for those
            // Also include blogs where the association is NULL (not assigned)
            $translationLanguageAccessor = \sprintf(
                '%s.%s.languageId',
                $association,
                $translationField->getPropertyName()
            );

            // Check if FK field exists (e.g., 'manufacturerId' for 'manufacturer')
            $foreignKeyField = $association . 'Id';
            $fkField = EntityDefinitionQueryHelper::getField($foreignKeyField, $definition, $definition->getEntityName());

            if (!$fkField instanceof FkField) {
                $filters[] = new EqualsFilter($translationLanguageAccessor, $context->getLanguageId());
                continue;
            }

            $filters[] = new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter($foreignKeyField, null),
                new EqualsFilter($translationLanguageAccessor, $context->getLanguageId()),
            ]);
        }

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters));
    }

    /**
     * @param array<string, BlogEntity> $existingBlogs
     * @param array<int, ConfigField> $configFields
     */
    private function assignParentBlogs(array $existingBlogs, array $configFields, Context $context): void
    {
        if (!\in_array('parent.name', array_column($configFields, 'field'), true)) {
            return;
        }

        /** @var array<string, list<BlogEntity>> $blogsByParentId */
        $blogsByParentId = [];
        foreach ($existingBlogs as $blog) {
            $parentId = $blog->getParentId();
            if ($parentId === null) {
                continue;
            }

            $blogsByParentId[$parentId][] = $blog;
        }

        $this->hydrateParentBlogs($blogsByParentId, $context);
    }

    /**
     * @param array<string, list<BlogEntity>> $blogsByParentId
     */
    private function hydrateParentBlogs(array $blogsByParentId, Context $context): void
    {
        if ($blogsByParentId === []) {
            return;
        }

        $criteria = new Criteria(array_keys($blogsByParentId));
        $criteria->setLimit(50);
        $criteria->addFields(['name']);

        $iterator = new RepositoryIterator($this->blogRepository, $context, $criteria);

        while ($parentBlogs = $iterator->fetch()) {
            foreach ($parentBlogs->getEntities() as $parent) {
                $parentBlog = new BlogEntity();
                $parentBlog->setId($parent->getId());
                $parentBlog->setTranslated($parent->getTranslated());

                $name = $parent->get('name');
                $parentBlog->setName(\is_string($name) ? $name : null);

                foreach ($blogsByParentId[$parentBlog->getId()] ?? [] as $blog) {
                    $blog->setParent($parentBlog);
                }
            }
        }
    }

    /**
     * @return array<int, ConfigField>
     */
    private function getConfigFields(string $languageId): array
    {
        if (isset($this->config[$languageId])) {
            return $this->config[$languageId];
        }

        $query = $this->connection->createQueryBuilder();
        $query->select('configField.field', 'configField.tokenize', 'configField.ranking', 'LOWER(HEX(config.language_id)) as language_id');
        $query->from('blog_search_config', 'config');
        $query->join('config', 'blog_search_config_field', 'configField', 'config.id = configField.blog_search_config_id');
        $query->andWhere('config.language_id IN (:languageIds)');
        $query->andWhere('configField.searchable = 1');

        $query->setParameter('languageIds', Uuid::fromHexToBytesList([$languageId, Defaults::LANGUAGE_SYSTEM]), ArrayParameterType::BINARY);

        /** @var list<ConfigField> $all */
        $all = $query->executeQuery()->fetchAllAssociative();

        $fields = array_filter($all, static fn (array $field) => $field['language_id'] === $languageId);

        if ($fields !== []) {
            $this->config[$languageId] = $fields;

            return $fields;
        }

        $fields = array_filter($all, static fn (array $field) => $field['language_id'] === Defaults::LANGUAGE_SYSTEM);
        $this->config[$languageId] = $fields;

        return $fields;
    }

    /**
     * Sort languages so default language comes first, then languages that don't inherit and last inherited languages
     *
     * @return LanguageEntity[]
     */
    private function sortLanguages(LanguageCollection $languages): array
    {
        $defaultLanguage = $languages->get(Defaults::LANGUAGE_SYSTEM);
        $languages->remove(Defaults::LANGUAGE_SYSTEM);

        return array_filter(array_merge(
            [$defaultLanguage],
            $languages->filterByProperty('parentId', null)->getElements(),
            $languages->filter(static fn (LanguageEntity $language) => $language->getParentId() !== null)->getElements()
        ));
    }
}
