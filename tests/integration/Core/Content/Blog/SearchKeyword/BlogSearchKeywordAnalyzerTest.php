<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\SearchKeyword;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigCollection;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\SearchKeyword\AnalyzedKeyword;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchKeywordAnalyzer;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogSearchKeywordAnalyzerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const CUSTOM_FIELDS = 'customFields';

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<BlogSearchConfigCollection>
     */
    private EntityRepository $blogSearchConfigRepository;

    /**
     * @var EntityRepository<CustomFieldSetCollection>
     */
    private EntityRepository $customFieldSetRepository;

    private Connection $connection;

    private Context $context;

    private IdsCollection $ids;

    private string $enSearchConfigId;

    private string $deSearchConfigId;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->customFieldSetRepository = static::getContainer()->get('custom_field_set.repository');
        $this->blogSearchConfigRepository = static::getContainer()->get('blog_search_config.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
        $this->createDataTest();
    }

    public function testCustomFields(): void
    {
        $config = [
            [
                'field' => 'customFields.field1',
                'tokenize' => true,
                'ranking' => 100,
            ],
            [
                'field' => 'blog.customFields.field2',
                'tokenize' => true,
                'ranking' => 100,
            ],
            [
                'field' => 'blog.customFields.field3',
                'tokenize' => true,
                'ranking' => 100,
            ],
            [
                'field' => 'blog.customFields.field4',
                'tokenize' => false,
                'ranking' => 100,
            ],
            [
                'field' => 'blog.customFields.field5',
                'tokenize' => false,
                'ranking' => 100,
            ],
            [
                'field' => 'blog.customFields.field6',
                'tokenize' => false,
                'ranking' => 100,
            ],
            [
                'field' => 'blog.customFields.nestedField.value',
                'tokenize' => false,
                'ranking' => 100,
            ],
            [
                'field' => 'customFields.notExists',
                'tokenize' => true,
                'ranking' => 100,
            ],
            [
                'field' => 'customFields',
                'tokenize' => true,
                'ranking' => 100,
            ],
        ];

        $blog = new BlogEntity();
        $blog->setCustomFields([
            'field1' => 'searchable',
            'field2' => 'match',
            'field3' => ['array'],
            'field4' => 10000000,
            'field5' => false,
            'field6' => 10.99999,
            'nestedField' => [
                'value' => 'nested',
                'second' => 'ignored',
            ],
            'ignored' => 'ignored',
        ]);

        $analyzer = static::getContainer()->get(BlogSearchKeywordAnalyzer::class);

        $result = $analyzer->analyze($blog, Context::createDefaultContext(), $config);

        $words = $result->map(static fn (AnalyzedKeyword $keyword) => $keyword->getKeyword());

        static::assertSame(
            ['searchable', 'match', 'array', '10000000', '10.99999', 'nested'],
            array_values($words)
        );
    }

    #[DataProvider('casesSearchBaseOnConfigField')]
    public function testInsertIntoSearchKeywordForEn(bool $searchable, bool $tokenize, float $ranking): void
    {
        $this->updateBlogSearchConfigField($this->enSearchConfigId, $searchable, $tokenize, $ranking);

        $blog = $this->getBlog();
        $configFields = $this->getConfigFieldsByLanguageId($this->enSearchConfigId);
        $analyzer = static::getContainer()->get(BlogSearchKeywordAnalyzer::class);
        $analyzer = $analyzer->analyze($blog, $this->context, $configFields);
        $analyzerResult = $analyzer->getKeys();
        sort($analyzerResult);

        $expected = [];
        if ($searchable && $tokenize) {
            $expected = [
                'test',
                'blog',
                'category',
                'manufacturer',
                123456123123123,
                'metadescription',
                'description',
                'f023204e895b4cec8492ec14194e10d2',
                'metatitle',
                'search',
                'keyword',
                'update',
                'ean',
                'tag2',
                'tag1',
                'red',
                'tet',
                'customfield',
                123456,
                123456789,
                'blog description',
                '123456789 category customfield',
                '123456789 manufacturer customfield',
                'metadescription test',
                'metatitle test',
                'search keyword update',
                'tag1 tag2',
                'test category',
                'test ean',
                'test blog',
                'tet customfield',
            ];

            foreach ($analyzer->getElements() as $keyword) {
                static::assertSame($ranking, $keyword->getRanking());
            }
        }

        if ($searchable && !$tokenize) {
            $expected = [
                'Search Keyword Update',
                'Test category',
                'manufacturer customfield',
                'category customfield',
                'f023204e895b4cec8492ec14194e10d2',
                'metaDescription test',
                'metaTitle test',
                'blog description',
                'red',
                'tag1',
                'tag2',
                'test',
                'test ean',
                'test blog',
                123456123123123,
                'tet CustomField',
                123456,
                123456789,
            ];

            foreach ($analyzer->getElements() as $keyword) {
                static::assertSame($ranking, $keyword->getRanking());
            }
        }

        if (!$searchable && $tokenize || !$searchable && !$tokenize) {
            $expected = [];
        }

        sort($expected);

        static::assertSame($expected, $analyzerResult);
    }

    #[DataProvider('casesSearchBaseOnConfigField')]
    public function testInsertIntoSearchKeywordForDe(bool $searchable, bool $tokenize, float $ranking): void
    {
        $this->updateBlogSearchConfigField($this->deSearchConfigId, $searchable, $tokenize, $ranking);

        $blog = $this->getBlog();
        $configFields = $this->getConfigFieldsByLanguageId($this->deSearchConfigId);
        $analyzer = static::getContainer()->get(BlogSearchKeywordAnalyzer::class);
        $analyzer = $analyzer->analyze($blog, $this->context, $configFields);
        $analyzerResult = $analyzer->getKeys();
        sort($analyzerResult);

        $expected = [];
        if ($searchable && $tokenize) {
            $expected = [
                'test',
                'blog',
                'category',
                123456123123123,
                'metadescription',
                'description',
                'f023204e895b4cec8492ec14194e10d2',
                'metatitle',
                'search',
                'keyword',
                'update',
                'ean',
                'tag2',
                'tag1',
                'red',
                'tet',
                'customfield',
                123456,
                123456789,
                'manufacturer',
                '123456789 category customfield',
                '123456789 manufacturer customfield',
                'metadescription test',
                'metatitle test',
                'blog description',
                'search keyword update',
                'tag1 tag2',
                'test category',
                'test ean',
                'test blog',
                'tet customfield',
            ];

            foreach ($analyzer->getElements() as $keyword) {
                static::assertSame($ranking, $keyword->getRanking());
            }
        }

        if ($searchable && !$tokenize) {
            $expected = [
                'Search Keyword Update',
                'Test category',
                'f023204e895b4cec8492ec14194e10d2',
                'metaDescription test',
                'metaTitle test',
                'blog description',
                'red',
                'tag1',
                'tag2',
                'test',
                'test ean',
                'test blog',
                123456123123123,
                'tet CustomField',
                123456,
                123456789,
                'manufacturer customfield',
                'category customfield',
            ];

            foreach ($analyzer->getElements() as $keyword) {
                static::assertSame($ranking, $keyword->getRanking());
            }
        }

        if (!$searchable && $tokenize || !$searchable && !$tokenize) {
            $expected = [];
        }

        sort($expected);

        static::assertSame($expected, $analyzerResult);
    }

    /**
     * @return array<string, array{bool, bool, float}>
     */
    public static function casesSearchBaseOnConfigField(): array
    {
        return [
            'searchable is true, tokenize is true, ranking is 500' => [true, true, 500.0],
            'searchable is true, tokenize is true, ranking is 600' => [true, true, 600.0],
            'searchable is true, tokenize is true, ranking is 700' => [true, true, 700.0],
            'searchable is false, tokenize is true, ranking is 500' => [false, true, 500.0],
            'searchable is true, tokenize is false, ranking is 500' => [true, false, 500.0],
            'searchable is true, tokenize is false, ranking is 1000' => [true, false, 1000.0],
            'searchable is true, tokenize is false, ranking is 1500' => [true, false, 1500.0],
            'searchable is false, tokenize is false, ranking is 500' => [false, false, 500.0],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getConfigFieldsByLanguageId(string $searchConfigId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('*');
        $query->from('blog_search_config_field');
        $query->andWhere('blog_search_config_id = :searchConfigId');
        $query->andWhere('searchable = 1');
        $query->setParameter('searchConfigId', Uuid::fromHexToBytes($searchConfigId));

        return $query->executeQuery()->fetchAllAssociative();
    }

    private function getBlog(): BlogEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->ids->get('blog_id')));
        $criteria->addAssociation('categories');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('properties');
        $criteria->addAssociation('tags');
        $criteria->addAssociation('customFieldSets');

        $blog = $this->blogRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($blog);

        return $blog;
    }

    private function createDataTest(): void
    {
        $this->enSearchConfigId = $this->getEnSearchConfig()->getId();
        $this->deSearchConfigId = $this->getDeSearchConfig()->getId();

        $this->createCustomFieldSet();

        $blogs = [
            [
                'id' => $this->ids->create('blog_id'),
                'name' => 'test blog',
                'description' => 'blog description',
                'blogNumber' => 'f023204e895b4cec8492ec14194e10d2',
                'manufacturerNumber' => '123456123123123',
                'ean' => 'test ean',
                'metaTitle' => 'metaTitle test',
                'metaDescription' => 'metaDescription test',
                'stock' => 10,
                'customFields' => [
                    'custom_test_field_1' => 'tet CustomField',
                    'custom_test_field_2' => 123456,
                ],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => [
                    'id' => $this->ids->create('manufacturer_id'),
                    'name' => 'test',
                    'customFields' => [
                        'custom_test_field_text' => 'manufacturer customfield',
                        'custom_test_field_int' => 123456789,
                    ],
                ],
                'categories' => [
                    [
                        'id' => $this->ids->create('category_id1'),
                        'name' => 'Test category',
                        'customFields' => [
                            'custom_test_field_text' => 'category customfield',
                            'custom_test_field_int' => 123456789,
                        ],
                    ],
                ],
                'tax' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test', 'taxRate' => 15],
                'customSearchKeywords' => ['Search Keyword Update'],
                'properties' => [
                    [
                        'id' => $this->ids->create('property_id'),
                        'name' => 'red',
                        'group' => ['id' => $this->ids->create('group_id'), 'name' => 'color'],
                    ],
                ],
                'tags' => [
                    ['id' => $this->ids->create('tag1'), 'name' => 'tag1'],
                    ['id' => $this->ids->create('tag2'), 'name' => 'tag2'],
                ],
                'customFieldSet' => [
                    'id' => $this->ids->get('custom_field_set_id'),
                ],
            ],
            [
                'id' => $this->ids->create('blog_id_2'),
                'name' => 'test blog',
                'description' => 'blog description',
                'blogNumber' => 'f023204e895b4cec8492ec14194e10d2.1',
                'manufacturerNumber' => '123456123123123',
                'ean' => 'test ean',
                'metaTitle' => 'metaTitle test',
                'metaDescription' => 'metaDescription test',
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => [
                    'id' => $this->ids->get('manufacturer_id'),
                    'name' => 'test',
                    'customFields' => [
                        'custom_test_field_text' => 'manufacturer customfield',
                        'custom_test_field_int' => 123456789,
                    ],
                ],
                'categories' => [
                    [
                        'id' => $this->ids->get('category_id1'),
                        'name' => 'Test category',
                        'customFields' => [
                            'custom_test_field_text' => 'category customfield',
                            'custom_test_field_int' => 123456789,
                        ],
                    ],
                ],
                'tax' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test', 'taxRate' => 15],
                'customSearchKeywords' => ['Search Keyword Update'],
                'options' => [
                    [
                        'id' => $this->ids->create('red'),
                        'groupId' => $this->ids->get('group_id'),
                        'name' => 'small',
                    ],
                ],
                'tags' => [
                    ['id' => $this->ids->get('tag1'), 'name' => 'tag1'],
                    ['id' => $this->ids->get('tag2'), 'name' => 'tag2'],
                ],
                'customFieldSet' => [
                    'id' => $this->ids->get('custom_field_set_id'),
                ],
            ],
        ];

        $this->blogRepository->create($blogs, $this->context);
    }

    private function createCustomFieldSet(): void
    {
        $customFieldSetData = [
            'id' => $this->ids->create('custom_field_set_id'),
            'name' => 'custom_Test',
            'config' => [
                'label' => [
                    'de-DE' => 'DE Test',
                    'en-GB' => 'EN Test',
                ],
            ],
            'customFields' => [
                [
                    'id' => $this->ids->create('custom_field_id1'),
                    'name' => 'custom_test_field_1',
                    'type' => 'text',
                    'config' => [
                        'type' => 'text',
                        'label' => [
                            'en-GB' => 'Text',
                        ],
                        'helpText' => [
                            'en-GB' => 'Text',
                        ],
                        'placeholder' => [
                            'en-GB' => 'Text',
                        ],
                        'componentName' => 'sw-field',
                        'customFieldType' => 'text',
                        'customFieldPosition' => 1,
                    ],
                    'active' => true,
                    'includeInSearch' => true,
                    'customFieldSetId' => $this->ids->get('custom_field_set_id'),
                    'blogSearchConfigFields' => [
                        [
                            'id' => Uuid::randomHex(),
                            'searchConfigId' => $this->enSearchConfigId,
                            'customFieldId' => $this->ids->get('custom_field_id1'),
                            'field' => self::CUSTOM_FIELDS . '.custom_test_field_1',
                            'tokenize' => false,
                            'searchable' => false,
                            'ranking' => 500,
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'searchConfigId' => $this->deSearchConfigId,
                            'customFieldId' => $this->ids->get('custom_field_id1'),
                            'field' => self::CUSTOM_FIELDS . '.custom_test_field_1',
                            'tokenize' => false,
                            'searchable' => false,
                            'ranking' => 500,
                        ],
                    ],
                ],
                [
                    'id' => $this->ids->create('custom_field_id2'),
                    'name' => 'custom_test_field_2',
                    'type' => 'int',
                    'config' => [
                        'type' => 'number',
                        'label' => [
                            'en-GB' => 'Test',
                        ],
                        'numberType' => 'int',
                        'placeholder' => [
                            'en-GB' => 'Type a number...',
                        ],
                        'componentName' => 'sw-field',
                        'customFieldType' => 'number',
                        'customFieldPosition' => 1,
                    ],
                    'active' => true,
                    'includeInSearch' => true,
                    'customFieldSetId' => $this->ids->get('custom_field_set_id'),
                    'blogSearchConfigFields' => [
                        [
                            'id' => Uuid::randomHex(),
                            'searchConfigId' => $this->enSearchConfigId,
                            'customFieldId' => $this->ids->get('custom_field_id2'),
                            'field' => self::CUSTOM_FIELDS . '.custom_test_field_2',
                            'tokenize' => false,
                            'searchable' => false,
                            'ranking' => 500,
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'searchConfigId' => $this->deSearchConfigId,
                            'customFieldId' => $this->ids->get('custom_field_id2'),
                            'field' => self::CUSTOM_FIELDS . '.custom_test_field_2',
                            'tokenize' => false,
                            'searchable' => false,
                            'ranking' => 500,
                        ],
                    ],
                ],
            ],
            'relations' => [
                [
                    'id' => Uuid::randomHex(),
                    'customFieldSetId' => $this->ids->get('custom_field_set_id'),
                    'entityName' => 'blog',
                ],
            ],
        ];

        $this->customFieldSetRepository->upsert([$customFieldSetData], $this->context);
    }

    private function getEnSearchConfig(): BlogSearchConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM));

        $blogSearchConfig = $this->blogSearchConfigRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($blogSearchConfig);

        return $blogSearchConfig;
    }

    private function getDeSearchConfig(): BlogSearchConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM)]
        ));

        $blogSearchConfig = $this->blogSearchConfigRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($blogSearchConfig);

        return $blogSearchConfig;
    }

    private function updateBlogSearchConfigField(
        string $searchConfigId,
        bool $searchable,
        bool $tokenize,
        float $ranking
    ): void {
        $this->connection->executeStatement(
            'UPDATE `blog_search_config_field`
                    SET `searchable` = :searchable, `tokenize` = :tokenize, `ranking` = :ranking
                    WHERE `blog_search_config_id` =:searchConfigId',
            [
                'searchConfigId' => Uuid::fromHexToBytes($searchConfigId),
                'searchable' => (int) $searchable,
                'tokenize' => (int) $tokenize,
                'ranking' => $ranking,
            ]
        );
    }
}
