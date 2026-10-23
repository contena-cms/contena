<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\SimpleQueryStringQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Search;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;

final class BlogAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly ElasticsearchFieldBuilder $fieldBuilder,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return BlogDefinition::ENTITY_NAME;
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $blogIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'blogNumber',
            'ean',
            'manufacturerNumber',
            'active',
            'manufacturerId',
            'price',
            'stock',
            'releaseDate',
        ]);

        $translations = $event->getPrimaryKeysWithPropertyChange(BlogTranslationDefinition::ENTITY_NAME, [
            'name',
            'customSearchKeywords',
        ]);

        $categories = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $event->getPrimaryKeysWithPropertyChange(BlogCategoryDefinition::ENTITY_NAME, [
            'categoryId',
        ]) : [];

        $visibilities = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $event->getPrimaryKeysWithPropertyChange(BlogVisibilityDefinition::ENTITY_NAME, [
            'channelId',
        ]) : [];

        $media = Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') ? $event->getPrimaryKeysWithPropertyChange(BlogMediaDefinition::ENTITY_NAME, [
            'mediaId',
        ]) : [];

        $tags = $event->getPrimaryKeysWithPropertyChange(BlogTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach (array_merge($translations, $tags, $visibilities, $categories, $media) as $pks) {
            if (isset($pks['blogId'])) {
                $blogIds[] = $pks['blogId'];
            }
        }

        return array_values(array_unique(array_filter($blogIds, '\is_string')));
    }

    public function getName(): string
    {
        return 'blog-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');
        $criteria = new Criteria($ids);
        $criteria->addAssociations(['options.group']);

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search($criteria, $context)->getEntities(),
        ];
    }

    public function globalCriteria(string $term, Search $criteria): Search
    {
        $splitTerms = explode(' ', $term);
        $lastPart = (string) end($splitTerms);
        $identifierTerm = mb_strtolower($term);
        $identifierFields = ['ean', 'blogNumber', 'manufacturerNumber'];
        $isPrefixTerm = preg_match('/^[\p{L}0-9]+$/Du', $lastPart) === 1;
        $textBoostedTerm = $isPrefixTerm ? $term . '*' : $term;

        foreach ($identifierFields as $field) {
            $criteria->addQuery(
                new TermQuery($field, $identifierTerm, ['boost' => SearchRanking::HIGH_SEARCH_RANKING]),
                BoolQuery::SHOULD
            );
        }

        $criteria->addQuery(
            new SimpleQueryStringQuery($textBoostedTerm, [
                'fields' => ['textBoosted'],
                'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                'lenient' => true,
            ]),
            BoolQuery::SHOULD
        );

        return $criteria;
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $languageFields = $this->fieldBuilder->translated(AbstractElasticsearchDefinition::KEYWORD_FIELD);

        $override = [
            'parentId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'available' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'name' => $languageFields,
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'sales' => AbstractElasticsearchDefinition::INT_FIELD,
            'type' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'states' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'blogNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'ean' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'manufacturerNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'manufacturerId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'stock' => AbstractElasticsearchDefinition::INT_FIELD,
            'price' => ['type' => 'object', 'dynamic' => true],
            'releaseDate' => ElasticsearchFieldBuilder::datetime(),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'updatedAt' => ElasticsearchFieldBuilder::datetime(),
            'categories' => ElasticsearchFieldBuilder::nested(),
            'tags' => ElasticsearchFieldBuilder::nested(),
            'manufacturer' => ElasticsearchFieldBuilder::nested([
                'name' => $languageFields,
            ]),
            'media' => ElasticsearchFieldBuilder::nested(),
            'visibilities' => ElasticsearchFieldBuilder::nested([
                'channelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
        ];

        $mapping['properties'] = array_merge($mapping['properties'] ?? [], $override);

        $mapping['dynamic_templates'][] = [
            'price_fields' => [
                'path_match' => 'price.*.*',
                'mapping' => ['type' => 'double'],
            ],
        ];

        return $mapping;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetch(array $ids): array
    {
        if (Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return $this->advancedFetch($ids);
        }

        $data = $this->connection->fetchAllAssociative(
            "
            SELECT LOWER(HEX(blog.id)) as id,
                   GROUP_CONCAT(DISTINCT translation.name ORDER BY NULL SEPARATOR '\n') as name,
                   CONCAT('[', GROUP_CONCAT(translation.custom_search_keywords), ']') as custom_search_keywords,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR ' ') as tags,
                   blog.blog_number,
                   blog.ean,
                   blog.manufacturer_number
            FROM blog
                INNER JOIN blog_translation AS translation
                    ON blog.id = translation.blog_id AND blog.version_id = translation.blog_version_id
                LEFT JOIN blog_tag
                    ON blog.id = blog_tag.blog_id AND blog.version_id = blog_tag.blog_version_id
                LEFT JOIN tag
                    ON blog_tag.tag_id = tag.id
            WHERE blog.id IN (:ids)
            AND blog.version_id = :versionId
            GROUP BY blog.id
        ",
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $names = $this->splitTranslatedNames(\is_string($row['name'] ?? null) ? $row['name'] : '');
            $textBoosted = (string) ($row['blog_number'] ?? '');

            if ($row['custom_search_keywords']) {
                $row['custom_search_keywords'] = json_decode((string) $row['custom_search_keywords'], true, 512, \JSON_THROW_ON_ERROR);
                $textBoosted = trim($textBoosted . ' ' . implode(' ', array_unique(array_merge(...$row['custom_search_keywords']))));
            }

            $id = (string) $row['id'];
            unset($row['name'], $row['custom_search_keywords']);
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = [
                'id' => $id,
                'textBoosted' => \strtolower($textBoosted),
                'text' => \strtolower($text),
                'completion' => $this->buildCompletion($names),
            ];
        }

        return $mapped;
    }

    /**
     * @return list<string>
     */
    private function splitTranslatedNames(string $concat): array
    {
        if ($concat === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $concat)),
            static fn (string $name): bool => $name !== ''
        ));
    }

    /**
     * @description to keep the writing fast we do a more complex fetch here only if the feature flag ENABLE_OPENSEARCH_FOR_ADMIN_API is enabled to reduce the number of joins in the sql query
     *
     * @param array<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function advancedFetch(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $binaryIds = array_values(Uuid::fromHexToBytesList($ids));

        $baseRows = $this->fetchBlogBaseRows($binaryIds, $versionId);

        if ($baseRows === []) {
            return [];
        }

        $blogIds = $this->collectHexColumnValues($baseRows, 'id');
        $parentIds = $this->collectHexColumnValues($baseRows, 'parentId');
        $translationIds = array_values(array_unique(array_merge($blogIds, $parentIds)));
        $translationsByBlogId = $this->fetchTranslationsByBlogIds($translationIds, $versionId);

        $manufacturerIds = $this->collectHexColumnValues($baseRows, 'manufacturerId');
        $manufacturerById = $this->fetchManufacturerTranslationsByIds($manufacturerIds, $versionId);

        $tagsByBlogId = $this->fetchTagsByBlogIds($binaryIds, $versionId);

        $visibilityIds = $this->collectHexColumnValues($baseRows, 'visibilitiesId');
        $visibilitiesByBlogId = $this->fetchVisibilitiesByIds($visibilityIds, $versionId);

        return $this->mapAdvancedRows(
            $baseRows,
            $translationsByBlogId,
            $manufacturerById,
            $tagsByBlogId,
            $visibilitiesByBlogId
        );
    }

    /**
     * @param list<string> $binaryIds
     *
     * @return list<array<string, mixed>>
     */
    private function fetchBlogBaseRows(array $binaryIds, string $versionId): array
    {
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(blog.id)) as id,
                   IFNULL(blog.active, parent.active) AS active,
                   blog.available AS available,
                   LOWER(HEX(blog.parent_id)) as parentId,
                   blog.blog_number as blogNumber,
                   blog.ean as ean,
                   blog.manufacturer_number as manufacturerNumber,
                   blog.sales as sales,
                   blog.type as type,
                   blog.states as states,
                   LOWER(HEX(blog.manufacturer)) AS manufacturerId,
                   LOWER(HEX(blog.visibilities)) AS visibilitiesId,
                   IFNULL(blog.category_ids, parent.category_ids) AS categoryIds,
                   blog.stock as stock,
                   IFNULL(blog.price, parent.price) AS priceRaw,
                   IFNULL(blog.release_date, parent.release_date) AS releaseDate,
                   LOWER(HEX(blog.cover)) AS mediaId,
                   blog.created_at as createdAt,
                   blog.updated_at as updatedAt
            FROM blog
                LEFT JOIN blog parent
                    ON blog.parent_id = parent.id
                    AND parent.version_id = :versionId
            WHERE blog.id IN (:ids)
            AND blog.version_id = :versionId
SQL,
            ['ids' => $binaryIds, 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $translationIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchTranslationsByBlogIds(array $translationIds, string $versionId): array
    {
        if ($translationIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(blog_translation.blog_id)) as id,
                   GROUP_CONCAT(DISTINCT blog_translation.name ORDER BY NULL SEPARATOR ' ') as name,
                   CONCAT(
                       '[',
                       IFNULL(
                           GROUP_CONCAT(
                               CASE
                                   WHEN blog_translation.name IS NOT NULL THEN JSON_OBJECT(
                                       'languageId', LOWER(HEX(blog_translation.language_id)),
                                       'name', blog_translation.name
                                   )
                               END
                               ORDER BY NULL
                           ),
                           ''
                       ),
                       ']'
                   ) as translatedNames,
                   CONCAT('[', IFNULL(GROUP_CONCAT(blog_translation.custom_search_keywords ORDER BY NULL), ''), ']') as customSearchKeywords
            FROM blog_translation
            WHERE blog_translation.blog_id IN (:ids)
            AND blog_translation.blog_version_id = :versionId
            AND (
                blog_translation.name IS NOT NULL
                OR blog_translation.custom_search_keywords IS NOT NULL
            )
            GROUP BY blog_translation.blog_id, blog_translation.blog_version_id
SQL,
            ['ids' => Uuid::fromHexToBytesList($translationIds), 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<string> $manufacturerIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchManufacturerTranslationsByIds(array $manufacturerIds, string $versionId): array
    {
        if ($manufacturerIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(blog_manufacturer_translation.blog_manufacturer_id)) as id,
                   JSON_ARRAYAGG(JSON_OBJECT(
                       'languageId', LOWER(HEX(blog_manufacturer_translation.language_id)),
                       'name', blog_manufacturer_translation.name
                   )) as translatedManufacturerNames
            FROM blog_manufacturer_translation
            WHERE blog_manufacturer_translation.blog_manufacturer_id IN (:ids)
            AND blog_manufacturer_translation.blog_manufacturer_version_id = :versionId
            GROUP BY blog_manufacturer_translation.blog_manufacturer_id, blog_manufacturer_translation.blog_manufacturer_version_id
SQL,
            ['ids' => Uuid::fromHexToBytesList($manufacturerIds), 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<string> $binaryIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchTagsByBlogIds(array $binaryIds, string $versionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(blog_tag.blog_id)) as id,
                   GROUP_CONCAT(DISTINCT tag.name ORDER BY NULL SEPARATOR ' ') as tags,
                   GROUP_CONCAT(LOWER(HEX(tag.id)) ORDER BY NULL SEPARATOR ' ') as tagIds
            FROM blog_tag
                LEFT JOIN tag
                    ON blog_tag.tag_id = tag.id
            WHERE blog_tag.blog_id IN (:ids)
            AND blog_tag.blog_version_id = :versionId
            GROUP BY blog_tag.blog_id, blog_tag.blog_version_id
SQL,
            ['ids' => $binaryIds, 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<string> $visibilityIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchVisibilitiesByIds(array $visibilityIds, string $versionId): array
    {
        if ($visibilityIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(blog_visibility.blog_id)) as id,
                   CONCAT(
                       '[',
                       GROUP_CONCAT(
                           DISTINCT JSON_OBJECT(
                               'channelId', LOWER(HEX(blog_visibility.channel_id))
                           ) ORDER BY NULL
                       ),
                       ']'
                   ) as visibilities
            FROM blog_visibility
            WHERE blog_visibility.blog_id IN (:ids)
            AND blog_visibility.blog_version_id = :versionId
            GROUP BY blog_visibility.blog_id, blog_visibility.blog_version_id
SQL,
            ['ids' => Uuid::fromHexToBytesList($visibilityIds), 'versionId' => $versionId],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $this->indexRowsById($rows);
    }

    /**
     * @param list<array<string, mixed>> $baseRows
     * @param array<string, array<string, mixed>> $translationsByBlogId
     * @param array<string, array<string, mixed>> $manufacturerById
     * @param array<string, array<string, mixed>> $tagsByBlogId
     * @param array<string, array<string, mixed>> $visibilitiesByBlogId
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapAdvancedRows(
        array $baseRows,
        array $translationsByBlogId,
        array $manufacturerById,
        array $tagsByBlogId,
        array $visibilitiesByBlogId
    ): array {
        $mapped = [];
        foreach ($baseRows as $row) {
            $id = \is_string($row['id'] ?? null) ? $row['id'] : null;
            if ($id === null) {
                continue;
            }

            $parentId = \is_string($row['parentId'] ?? null) ? $row['parentId'] : null;
            $manufacturerId = \is_string($row['manufacturerId'] ?? null) ? $row['manufacturerId'] : null;
            $visibilitiesId = \is_string($row['visibilitiesId'] ?? null) ? $row['visibilitiesId'] : null;

            $ownTranslation = $translationsByBlogId[$id] ?? null;
            $parentTranslation = $parentId !== null ? ($translationsByBlogId[$parentId] ?? null) : null;

            $name = \is_string($ownTranslation['name'] ?? null) && $ownTranslation['name'] !== ''
                ? $ownTranslation['name']
                : (\is_string($parentTranslation['name'] ?? null) ? $parentTranslation['name'] : '');
            $translatedNamesEncoded = \is_string($ownTranslation['translatedNames'] ?? null) && $ownTranslation['translatedNames'] !== '[]'
                ? $ownTranslation['translatedNames']
                : (\is_string($parentTranslation['translatedNames'] ?? null) ? $parentTranslation['translatedNames'] : '');
            $customSearchKeywordsEncoded = \is_string($ownTranslation['customSearchKeywords'] ?? null) && $ownTranslation['customSearchKeywords'] !== '[]'
                ? $ownTranslation['customSearchKeywords']
                : (\is_string($parentTranslation['customSearchKeywords'] ?? null) ? $parentTranslation['customSearchKeywords'] : '');
            $translatedManufacturerNamesEncoded = ($manufacturerId !== null && \is_string($manufacturerById[$manufacturerId]['translatedManufacturerNames'] ?? null))
                ? $manufacturerById[$manufacturerId]['translatedManufacturerNames']
                : '';
            $tags = \is_string($tagsByBlogId[$id]['tags'] ?? null) ? $tagsByBlogId[$id]['tags'] : '';
            $tagIds = \is_string($tagsByBlogId[$id]['tagIds'] ?? null) ? $tagsByBlogId[$id]['tagIds'] : '';
            $visibilitiesEncoded = ($visibilitiesId !== null && \is_string($visibilitiesByBlogId[$visibilitiesId]['visibilities'] ?? null))
                ? $visibilitiesByBlogId[$visibilitiesId]['visibilities']
                : '';

            $blogNumber = \is_string($row['blogNumber'] ?? null) ? $row['blogNumber'] : '';
            $textBoosted = $blogNumber;
            if ($customSearchKeywordsEncoded !== '') {
                $customSearchKeywords = json_decode($customSearchKeywordsEncoded, true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($customSearchKeywords) && $customSearchKeywords !== []) {
                    $textBoosted = trim($textBoosted . ' ' . implode(' ', array_unique(array_merge(...$customSearchKeywords))));
                }
            }

            $translatedNames = $this->decodeTranslatedValues($translatedNamesEncoded);
            $states = ElasticsearchIndexingUtils::parseJson(['states' => $row['states'] ?? null], 'states');
            $categoryIds = ElasticsearchIndexingUtils::parseJson(['categoryIds' => $row['categoryIds'] ?? null], 'categoryIds');
            $visibilities = ElasticsearchIndexingUtils::parseJson(['visibilities' => $visibilitiesEncoded ?: null], 'visibilities');
            $parsedTagIds = $this->parseTagIds(['tagIds' => $tagIds]);
            $price = $this->parsePrice($row);
            $textParts = array_filter([
                $tags,
                $blogNumber,
                \is_string($row['ean'] ?? null) ? $row['ean'] : null,
                \is_string($row['manufacturerNumber'] ?? null) ? $row['manufacturerNumber'] : null,
                $id,
            ]);

            $mapped[$id] = [
                'id' => $id,
                'textBoosted' => \strtolower($textBoosted),
                'text' => \strtolower(implode(' ', $textParts)),
                'completion' => $this->buildCompletion(array_values($translatedNames) ?: [$name]),
                'name' => $translatedNames,
                'parentId' => $parentId,
                'blogNumber' => \is_string($row['blogNumber'] ?? null) ? $row['blogNumber'] : null,
                'ean' => \is_string($row['ean'] ?? null) ? $row['ean'] : null,
                'manufacturerNumber' => \is_string($row['manufacturerNumber'] ?? null) ? $row['manufacturerNumber'] : null,
                'manufacturerId' => $manufacturerId,
                'sales' => (int) $row['sales'],
                'active' => (bool) $row['active'],
                'available' => (bool) $row['available'],
                'stock' => (int) $row['stock'],
                'price' => $price,
                'type' => $row['type'] ?? null,
                'states' => $states,
                'manufacturer' => $manufacturerId ? [
                    'id' => $manufacturerId,
                    'name' => $this->decodeTranslatedValues($translatedManufacturerNamesEncoded),
                ] : null,
                'categories' => array_map(static function (string $categoryId): array {
                    return [
                        'id' => $categoryId,
                        'versionId' => Defaults::LIVE_VERSION,
                        '_count' => 1,
                    ];
                }, $categoryIds),
                'visibilities' => array_map(static function (array $visibility): array {
                    return array_merge(['_count' => 1], $visibility);
                }, $visibilities),
                'media' => \is_string($row['mediaId'] ?? null) ? [['id' => $row['mediaId'], '_count' => 1]] : [],
                'tags' => $parsedTagIds,
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
                'updatedAt' => $this->formatDateTime($row, 'updatedAt'),
                'releaseDate' => $this->formatDateTime($row, 'releaseDate'),
            ];
        }

        return $mapped;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<string>
     */
    private function collectHexColumnValues(array $rows, string $column): array
    {
        return array_values(array_unique(array_filter(
            array_column($rows, $column),
            static fn (mixed $value): bool => \is_string($value) && Uuid::isValid($value)
        )));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexRowsById(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $id = \is_string($row['id'] ?? null) ? $row['id'] : null;
            if ($id === null || !Uuid::isValid($id)) {
                continue;
            }
            $indexed[$id] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, array{gross: float, net: float}>|null
     */
    private function parsePrice(array $row): ?array
    {
        $raw = $row['priceRaw'] ?? null;

        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        $prices = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($prices)) {
            return null;
        }

        $result = [];
        foreach ($prices as $key => $priceData) {
            if (!\is_array($priceData) || !isset($priceData['gross'])) {
                continue;
            }

            $currencyId = \is_string($key) && str_starts_with($key, 'c') ? substr($key, 1) : $key;

            $result['c_' . $currencyId] = [
                'gross' => (float) $priceData['gross'],
                'net' => (float) ($priceData['net'] ?? 0),
            ];
        }

        return $result !== [] ? $result : null;
    }
}
