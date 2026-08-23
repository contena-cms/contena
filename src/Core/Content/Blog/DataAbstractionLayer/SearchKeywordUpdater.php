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
                array_values(array_filter([$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM])),
                $context->getVersionId(),
                true,
                tenantId: $context->getTenantId(),
                globalTenantAccess: $context->hasGlobalTenantAccess(),
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
        $configFields = $this->getConfigFields($context->getLanguageId(), $context->getTenantId());

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());
        $tenantId = $context->getTenantId() !== null ? Uuid::fromHexToBytes($context->getTenantId()) : null;

        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->delete($ids, $context->getLanguageId(), $context->getVersionId(), $context->getTenantId());

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

        foreach ($existingBlogs as $blog) {
            $analyzed = $this->analyzer->analyze($blog, $context, $configFields);

            $blogId = Uuid::fromHexToBytes($blog->getId());

            foreach ($analyzed as $keyword) {
                $keywords[] = [
                    'tenant_id' => $tenantId,
                    'id' => Uuid::randomBytes(),
                    'version_id' => $versionId,
                    'blog_version_id' => $versionId,
                    'language_id' => $languageId,
                    'blog_id' => $blogId,
                    'keyword' => $keyword->getKeyword(),
                    'ranking' => $keyword->getRanking(),
                    'created_at' => $now,
                ];
                $key = ($context->getTenantId() ?? 'global') . $keyword->getKeyword() . $languageId;
                $dictionary[$key] = [
                    'tenant_id' => $tenantId,
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
    private function delete(array $ids, string $languageId, string $versionId, ?string $tenantId): void
    {
        $bytes = Uuid::fromHexToBytesList($ids);

        $params = [
            'ids' => $bytes,
            'language' => Uuid::fromHexToBytes($languageId),
            'versionId' => Uuid::fromHexToBytes($versionId),
        ];
        $tenantFilter = 'tenant_id IS NULL';
        if ($tenantId !== null) {
            $tenantFilter = 'tenant_id = :tenantId';
            $params['tenantId'] = Uuid::fromHexToBytes($tenantId);
        }

        RetryableQuery::retryable($this->connection, function () use ($params, $tenantFilter): void {
            $this->connection->executeStatement(
                'DELETE FROM blog_search_keyword WHERE blog_id IN (:ids) AND language_id = :language AND version_id = :versionId AND ' . $tenantFilter,
                $params,
                ['ids' => ArrayParameterType::BINARY]
            );
        });
    }

    /**
     * @param list<array{tenant_id: string|null, id: string, version_id: string, blog_version_id: string, language_id: string, blog_id: string, keyword: string, ranking: float, created_at: string}> $keywords
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
     * @param array<string, array{tenant_id: string|null, id: string, language_id: string, keyword: string}> $dictionary
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
        // (current language, its parent and the system default). A channel language may
        // inherit from a parent language that is not itself indexed (e.g. de-CH inheriting de-DE),
        // in which case the carried-over keywords of the parent language are not available and the
        // blog must be fetched here so its inherited translation can be indexed.
        $filters = [
            new EqualsAnyFilter('translations.languageId', $context->getLanguageIdChain()),
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

            // Check whether the association has a direct foreign-key field.
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
     * @return array<int, ConfigField>
     */
    private function getConfigFields(string $languageId, ?string $tenantId): array
    {
        $configKey = ($tenantId ?? 'global') . ':' . $languageId;
        if (isset($this->config[$configKey])) {
            return $this->config[$configKey];
        }

        foreach (array_unique([$languageId, Defaults::LANGUAGE_SYSTEM]) as $candidateLanguageId) {
            foreach ($this->tenantScopes($tenantId) as $candidateTenantId) {
                $fields = $this->fetchConfigFields($candidateLanguageId, $candidateTenantId);
                if ($fields !== []) {
                    return $this->config[$configKey] = $fields;
                }
            }
        }

        return $this->config[$configKey] = [];
    }

    /**
     * @return array<int, ConfigField>
     */
    private function fetchConfigFields(string $languageId, ?string $tenantId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('configField.field', 'configField.tokenize', 'configField.ranking', 'LOWER(HEX(config.language_id)) as language_id');
        $query->from('blog_search_config', 'config');
        $query->join('config', 'blog_search_config_field', 'configField', 'config.id = configField.blog_search_config_id');
        $query->andWhere('config.language_id = :languageId');
        $query->andWhere('configField.searchable = 1');
        if ($tenantId !== null) {
            $query->andWhere('config.tenant_id = :tenantId');
            $query->andWhere('configField.tenant_id = :tenantId');
            $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        } else {
            $query->andWhere('config.tenant_id IS NULL');
            $query->andWhere('configField.tenant_id IS NULL');
        }

        $query->setParameter('languageId', Uuid::fromHexToBytes($languageId));

        /** @var list<ConfigField> $all */
        $all = $query->executeQuery()->fetchAllAssociative();

        return $all;
    }

    /**
     * @return list<string|null>
     */
    private function tenantScopes(?string $tenantId): array
    {
        return $tenantId === null ? [null] : [$tenantId, null];
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
