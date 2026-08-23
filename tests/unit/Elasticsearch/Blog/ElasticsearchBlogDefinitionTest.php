<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\Language\ChannelLanguageLoader;
use Contena\Core\System\Language\LanguageLoaderInterface;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Elasticsearch\Blog\BlogSearchQueryBuilder;
use Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Contena\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Contena\Tests\Unit\Core\System\Language\Stubs\StaticChannelLanguageLoader;
use Contena\Tests\Unit\Core\System\Language\Stubs\StaticLanguageLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ElasticsearchBlogDefinition::class)]
class ElasticsearchBlogDefinitionTest extends TestCase
{
    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testMapping(): void
    {
        $languageLoader = new StaticLanguageLoader([
            'lang_en' => ['id' => 'lang_en', 'parentId' => 'parentId', 'code' => 'en-GB'],
            'lang_de' => ['id' => 'lang_de', 'parentId' => 'parentId', 'code' => 'de-DE'],
        ]);
        $channelLanguageLoader = new StaticChannelLanguageLoader([
            'lang_en' => [TestDefaults::CHANNEL],
            'lang_de' => [TestDefaults::CHANNEL],
        ]);
        $parameterBag = new ParameterBag([
            'elasticsearch.blog.custom_fields_mapping' => [
                'bool' => CustomFieldTypes::BOOL,
                'int' => CustomFieldTypes::INT,
            ],
        ]);
        $connection = static::createStub(Connection::class);
        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $fieldBuilder = new ElasticsearchFieldBuilder($languageLoader, $utils, [
            'en' => 'sw_english_analyzer',
            'de' => 'sw_german_analyzer',
        ]);

        $definition = new ElasticsearchBlogDefinition(
            $this->getDefinitionRegistry()->get(BlogDefinition::class),
            $connection,
            static::createStub(BlogSearchQueryBuilder::class),
            $fieldBuilder,
            new ElasticsearchFieldMapper($utils),
            $channelLanguageLoader,
            false,
            'dev',
            static::createStub(LanguageLoaderInterface::class)
        );

        $mapping = $definition->getMapping(Context::createDefaultContext());

        static::assertSame([
            'id',
            'tenantId',
            'name',
            'description',
            'descriptionTeaser',
            'keywords',
            'metaTitle',
            'metaDescription',
            'customSearchKeywords',
            'categories',
            'categoriesRo',
            'active',
            'type',
            'categoryTree',
            'categoryIds',
            'tagIds',
            'autoIncrement',
            'releaseDate',
            'createdAt',
            'tags',
            'visibilities',
            'coverId',
            'openGraphMediaId',
            'customFields',
            'visibility_' . TestDefaults::CHANNEL,
        ], array_keys($mapping['properties']));
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD, $mapping['properties']['id']);
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD, $mapping['properties']['tenantId']);
        static::assertSame(AbstractElasticsearchDefinition::BOOLEAN_FIELD, $mapping['properties']['active']);
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD, $mapping['properties']['type']);
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD, $mapping['properties']['categoryIds']);
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD, $mapping['properties']['tagIds']);
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD, $mapping['properties']['coverId']);
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD, $mapping['properties']['openGraphMediaId']);
        static::assertArrayHasKey('dynamic_templates', $mapping);
        static::assertSame([
            [
                'long_to_double' => [
                    'match_mapping_type' => 'long',
                    'mapping' => ['type' => 'double'],
                ],
            ],
        ], $mapping['dynamic_templates']);
        static::assertArrayHasKey('lang_en', $mapping['properties']['name']['properties']);
        static::assertArrayHasKey('lang_de', $mapping['properties']['name']['properties']);
        static::assertArrayHasKey('lang_en', $mapping['properties']['customFields']['properties']);
        static::assertArrayHasKey('lang_de', $mapping['properties']['customFields']['properties']);
    }

    public function testMappingCustomFields(): void
    {
        $connection = static::createStub(Connection::class);
        $languageLoader = new StaticLanguageLoader([
            'lang_en' => ['id' => 'lang_en', 'parentId' => 'parentId', 'code' => 'en-GB'],
            'lang_de' => ['id' => 'lang_de', 'parentId' => 'parentId', 'code' => 'de-DE'],
        ]);
        $channelLanguageLoader = new StaticChannelLanguageLoader([
            'lang_en' => [TestDefaults::CHANNEL],
            'lang_de' => [TestDefaults::CHANNEL],
        ]);
        $parameterBag = new ParameterBag([
            'elasticsearch.blog.custom_fields_mapping' => [
                'test1' => CustomFieldTypes::BOOL,
                'test2' => CustomFieldTypes::TEXT,
            ],
        ]);
        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $fieldBuilder = new ElasticsearchFieldBuilder($languageLoader, $utils, [
            'en' => 'sw_english_analyzer',
            'de' => 'sw_german_analyzer',
        ]);
        $definition = new ElasticsearchBlogDefinition(
            $this->getDefinitionRegistry()->get(BlogDefinition::class),
            $connection,
            static::createStub(BlogSearchQueryBuilder::class),
            $fieldBuilder,
            new ElasticsearchFieldMapper($utils),
            $channelLanguageLoader,
            false,
            'dev',
            static::createStub(LanguageLoaderInterface::class)
        );

        $customFields = $definition->getMapping(Context::createDefaultContext())['properties']['customFields'];

        static::assertSame(['type' => 'boolean'], $customFields['properties']['lang_en']['properties']['test1']);
        static::assertSame(['type' => 'boolean'], $customFields['properties']['lang_de']['properties']['test1']);
        static::assertSame('keyword', $customFields['properties']['lang_en']['properties']['test2']['type']);
        static::assertSame('keyword', $customFields['properties']['lang_de']['properties']['test2']['type']);
    }

    public function testGetDefinition(): void
    {
        $definition = $this->getDefinitionRegistry()->get(BlogDefinition::class);
        static::assertInstanceOf(BlogDefinition::class, $definition);

        $esDefinition = new ElasticsearchBlogDefinition(
            $definition,
            static::createStub(Connection::class),
            static::createStub(BlogSearchQueryBuilder::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            static::createStub(ElasticsearchFieldMapper::class),
            static::createStub(ChannelLanguageLoader::class),
            false,
            'dev',
            static::createStub(LanguageLoaderInterface::class)
        );

        static::assertSame($definition, $esDefinition->getEntityDefinition());
    }

    public function testBuildTermQueryUsingSearchQueryBuilder(): void
    {
        $searchQueryBuilder = static::createStub(BlogSearchQueryBuilder::class);
        $boolQuery = new BoolQuery();
        $boolQuery->add(new MatchQuery('name', 'test'));
        $searchQueryBuilder->method('build')->willReturn($boolQuery);

        $definition = $this->getDefinitionRegistry()->get(BlogDefinition::class);
        static::assertInstanceOf(BlogDefinition::class, $definition);
        $utils = new ElasticsearchIndexingUtils(static::createStub(Connection::class), new EventDispatcher(), new ParameterBag([]));
        $esDefinition = new ElasticsearchBlogDefinition(
            $definition,
            static::createStub(Connection::class),
            $searchQueryBuilder,
            new ElasticsearchFieldBuilder(new StaticLanguageLoader([]), $utils, []),
            new ElasticsearchFieldMapper($utils),
            static::createStub(ChannelLanguageLoader::class),
            false,
            'dev',
            static::createStub(LanguageLoaderInterface::class)
        );

        $criteria = new Criteria();
        $criteria->setTerm('test');

        static::assertSame([
            'match' => [
                'name' => ['query' => 'test'],
            ],
        ], $esDefinition->buildTermQuery(Context::createDefaultContext(), $criteria)->toArray());
    }

    public function testFetching(): void
    {
        $definition = $this->createDefinition(
            $this->getConnection(),
            new StaticChannelLanguageLoader([Defaults::LANGUAGE_SYSTEM => [TestDefaults::CHANNEL]])
        );
        $uuid = $this->ids->get('blog-1');

        $documents = $definition->fetch([$uuid], Context::createDefaultContext());

        static::assertArrayHasKey($uuid, $documents);
        $document = $documents[$uuid];
        static::assertSame($uuid, $document['id']);
        static::assertNull($document['tenantId']);
        static::assertSame(1.0, $document['autoIncrement']);
        static::assertTrue($document['active']);
        static::assertSame(BlogDefinition::TYPE_POST, $document['type']);
        static::assertSame('Test', $document['name'][Defaults::LANGUAGE_SYSTEM]);
        static::assertSame('Description', $document['description'][Defaults::LANGUAGE_SYSTEM]);
        static::assertSame('Teaser', $document['descriptionTeaser'][Defaults::LANGUAGE_SYSTEM]);
        static::assertSame(['category-1'], $document['categoryTree']);
        static::assertSame(['category-1'], $document['categoryIds']);
        static::assertSame(['tag-1'], $document['tagIds']);
        static::assertSame('cover-1', $document['coverId']);
        static::assertSame('open-graph-1', $document['openGraphMediaId']);
        static::assertSame(30, $document['visibility_' . TestDefaults::CHANNEL]);
        static::assertSame([
            ['id' => 'tag-1', 'name' => 'Tag', '_count' => 1],
        ], $document['tags']);
        static::assertSame([
            ['visibility' => 30, 'channelId' => TestDefaults::CHANNEL, '_count' => 1],
        ], $document['visibilities']);
    }

    public function testFetchingWithChannelLanguageMissingDefaultLang(): void
    {
        $languageId = Uuid::randomHex();
        $definition = $this->createDefinition(
            $this->getConnection(2),
            new StaticChannelLanguageLoader([$languageId => [TestDefaults::CHANNEL]])
        );
        $uuid = $this->ids->get('blog-1');

        $documents = $definition->fetch([$uuid], Context::createDefaultContext());

        static::assertSame('Test', $documents[$uuid]['name'][Defaults::LANGUAGE_SYSTEM]);
    }

    public function testFetchFormatsCustomFieldsAndRemovesNotMappedFields(): void
    {
        $connection = $this->getConnection();
        $languageLoader = new StaticLanguageLoader([
            Defaults::LANGUAGE_SYSTEM => [
                'id' => Defaults::LANGUAGE_SYSTEM,
                'parentId' => 'parentId',
                'code' => 'en-GB',
            ],
        ]);
        $parameterBag = new ParameterBag([
            'elasticsearch.blog.custom_fields_mapping' => [
                'bool' => CustomFieldTypes::BOOL,
                'int' => CustomFieldTypes::INT,
            ],
        ]);
        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $definition = new ElasticsearchBlogDefinition(
            $this->getDefinitionRegistry()->get(BlogDefinition::class),
            $connection,
            static::createStub(BlogSearchQueryBuilder::class),
            new ElasticsearchFieldBuilder($languageLoader, $utils, []),
            new ElasticsearchFieldMapper($utils),
            new StaticChannelLanguageLoader([Defaults::LANGUAGE_SYSTEM => [TestDefaults::CHANNEL]]),
            false,
            'dev',
            $languageLoader
        );
        $uuid = $this->ids->get('blog-1');

        $documents = $definition->fetch([$uuid], Context::createDefaultContext());

        $customFields = $documents[$uuid]['customFields'][Defaults::LANGUAGE_SYSTEM];
        static::assertTrue($customFields['bool']);
        static::assertSame(2.0, $customFields['int']);
        static::assertArrayNotHasKey('unknown', $customFields);
    }

    private function createDefinition(Connection $connection, ChannelLanguageLoader $channelLanguageLoader): ElasticsearchBlogDefinition
    {
        $definition = $this->getDefinitionRegistry()->get(BlogDefinition::class);
        static::assertInstanceOf(BlogDefinition::class, $definition);

        return new ElasticsearchBlogDefinition(
            $definition,
            $connection,
            static::createStub(BlogSearchQueryBuilder::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            static::createStub(ElasticsearchFieldMapper::class),
            $channelLanguageLoader,
            false,
            'dev',
            static::createStub(LanguageLoaderInterface::class)
        );
    }

    private function getConnection(int $numberOfTranslations = 1): Stub&Connection
    {
        $connection = static::createStub(Connection::class);
        $calls = [[
            $this->ids->get('blog-1') => [
                'id' => $this->ids->get('blog-1'),
                'tenantId' => null,
                'active' => true,
                'type' => BlogDefinition::TYPE_POST,
                'autoIncrement' => 1,
                'releaseDate' => '2026-08-01 12:00:00.000',
                'createdAt' => '2026-07-01 12:00:00.000',
                'categoryTree' => '["category-1"]',
                'categoryIds' => '["category-1"]',
                'tagIds' => '["tag-1"]',
                'coverId' => 'cover-1',
                'openGraphMediaId' => 'open-graph-1',
                'tags' => '[{"id":"tag-1","name":"<b>Tag</b>"}]',
                'visibilities' => '[{"visibility":30,"channelId":"' . TestDefaults::CHANNEL . '"}]',
            ],
        ]];

        for ($i = 0; $i < $numberOfTranslations; ++$i) {
            $calls[] = [
                $this->ids->get('blog-1') => [
                    'id' => $this->ids->get('blog-1'),
                    'name' => 'Test',
                    'description' => 'Description',
                    'descriptionTeaser' => 'Teaser',
                    'keywords' => 'Keyword',
                    'metaTitle' => 'Meta title',
                    'metaDescription' => 'Meta description',
                    'customSearchKeywords' => '["search"]',
                    'customFields' => '{"bool":"1","int":2,"unknown":"foo"}',
                    'categories' => '[{"id":"category-1","languageId":"' . Defaults::LANGUAGE_SYSTEM . '","name":"Category"}]',
                ],
            ];
        }

        $connection->method('fetchAllAssociativeIndexed')->willReturnOnConsecutiveCalls(...$calls);

        return $connection;
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class, BlogTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
