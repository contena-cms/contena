<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Contena\Elasticsearch\Blog\ElasticsearchOptimizeSwitch;
use Contena\Elasticsearch\Blog\BlogCriteriaParser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(BlogCriteriaParser::class)]
class BlogCriteriaParserTest extends TestCase
{
    private EntityDefinitionQueryHelper $helper;

    private CustomFieldService&Stub $customFieldService;

    private CriteriaParser&MockObject $decoratedParser;

    private EntityDefinition $blogDefinition;

    private EntityDefinition $categoryDefinition;

    private Context $context;

    protected function setUp(): void
    {
        $registry = $this->getRegistry();

        $this->helper = new EntityDefinitionQueryHelper();
        $this->customFieldService = static::createStub(CustomFieldService::class);
        $this->decoratedParser = $this->createMock(CriteriaParser::class);
        $this->blogDefinition = $registry->getByEntityName(BlogDefinition::ENTITY_NAME);
        $this->categoryDefinition = $registry->getByEntityName(CategoryDefinition::ENTITY_NAME);
        $this->context = new Context(new SystemSource());
    }

    public function testParseFilterWithNonBlogDefinition(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $filter = new EqualsFilter('name', 'test');

        $this->decoratedParser
            ->expects($this->never())
            ->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->categoryDefinition, 'root', $this->context);

        static::assertInstanceOf(TermQuery::class, $result);
        static::assertSame([
            'term' => [
                'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'test',
            ],
        ], $result->toArray());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testParseFilterDelegatesToTheDecoratedParserWhileTheOptimizationIsOff(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $filter = new BlogAvailableFilter('sales-channel-id');
        $expectedBuilder = static::createStub(BuilderInterface::class);

        $this->decoratedParser
            ->expects($this->once())
            ->method('parseFilter')
            ->with($filter, $this->blogDefinition, 'root', $this->context)
            ->willReturn($expectedBuilder);

        $result = $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertSame($expectedBuilder, $result);
    }

    public function testParseBlogAvailableFilterWithOptimizationDisabled(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $channelId = Uuid::randomHex();
        $filter = new BlogAvailableFilter($channelId, 30);
        $expectedBuilder = static::createStub(BuilderInterface::class);

        $this->decoratedParser
            ->expects($this->once())
            ->method('parseFilter')
            ->with($filter, $this->blogDefinition, 'root', $this->context)
            ->willReturn($expectedBuilder);

        // Feature::fake([]) forces the flag off so the deprecated pre-v6.8 branch is exercised
        // regardless of the suite's ambient v6.8.0.0 state (the test runs under major too).
        $result = Feature::fake([], fn () => $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context));

        static::assertSame($expectedBuilder->toArray(), $result->toArray());
    }

    public function testParseBlogAvailableFilterWithOptimizationEnabled(): void
    {
        $storage = new ArrayKeyValueStorage([
            ElasticsearchOptimizeSwitch::FLAG => true,
        ]);
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $channelId = Uuid::randomHex();
        $visibility = 30;
        $filter = new BlogAvailableFilter($channelId, $visibility);

        $this->decoratedParser->expects($this->never())->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertInstanceOf(BoolQuery::class, $result);

        $queryArray = $result->toArray();

        static::assertArrayHasKey('bool', $queryArray);
        static::assertArrayHasKey('must', $queryArray['bool']);
        static::assertCount(2, $queryArray['bool']['must']);

        $activeQuery = $queryArray['bool']['must'][0];
        static::assertArrayHasKey('term', $activeQuery);
        static::assertArrayHasKey('active', $activeQuery['term']);
        static::assertTrue($activeQuery['term']['active']);

        $visibilityQuery = $queryArray['bool']['must'][1];
        static::assertArrayHasKey('range', $visibilityQuery);
        static::assertArrayHasKey('visibility_' . $channelId, $visibilityQuery['range']);
        static::assertArrayHasKey('gte', $visibilityQuery['range']['visibility_' . $channelId]);
        static::assertSame($visibility, $visibilityQuery['range']['visibility_' . $channelId]['gte']);
    }

    public function testParseBlogAvailableFilterWithoutActiveFilter(): void
    {
        $storage = new ArrayKeyValueStorage([
            ElasticsearchOptimizeSwitch::FLAG => true,
        ]);
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $channelId = Uuid::randomHex();
        $visibility = 30;
        $filter = new BlogAvailableFilter($channelId, $visibility);

        $queries = $filter->getQueries();
        array_pop($queries);
        $filter->assign(['queries' => $queries]);

        $this->decoratedParser->expects($this->never())->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertInstanceOf(BoolQuery::class, $result);

        $queryArray = $result->toArray();

        static::assertSame([
            'range' => [
                'visibility_' . $channelId => ['gte' => $visibility],
            ],
        ], $queryArray);
    }

    public function testParseCategoriesRoIdEqualsFilter(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $categoryId = Uuid::randomHex();
        $filter = new EqualsFilter('categoriesRo.id', $categoryId);

        $this->decoratedParser->expects($this->never())->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertInstanceOf(TermQuery::class, $result);

        $queryArray = $result->toArray();
        static::assertArrayHasKey('term', $queryArray);
        static::assertArrayHasKey('categoryTree', $queryArray['term']);
        static::assertSame($categoryId, $queryArray['term']['categoryTree']);
    }

    public function testParseCategoriesRoIdEqualsFilterWithNullValue(): void
    {
        $storage = new ArrayKeyValueStorage([
            ElasticsearchOptimizeSwitch::FLAG => true,
        ]);

        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $filter = new EqualsFilter('categoriesRo.id', null);

        $this->decoratedParser
            ->expects($this->never())
            ->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertInstanceOf(BoolQuery::class, $result);
        $queryArray = $result->toArray();
        static::assertArrayHasKey('bool', $queryArray);
        static::assertArrayHasKey('must_not', $queryArray['bool']);
        static::assertIsArray($queryArray['bool']['must_not']);
        static::assertSame([
            'exists' => [
                'field' => 'categoryTree',
            ],
        ], $queryArray['bool']['must_not'][0]);
    }

    public function testParseCategoriesRoIdEqualsAnyFilter(): void
    {
        $storage = new ArrayKeyValueStorage([
            ElasticsearchOptimizeSwitch::FLAG => true,
        ]);
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $firstCategoryId = Uuid::randomHex();
        $secondCategoryId = Uuid::randomHex();
        $filter = new EqualsAnyFilter('categoriesRo.id', [$firstCategoryId, $secondCategoryId]);

        $this->decoratedParser->expects($this->never())->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertInstanceOf(TermsQuery::class, $result);
        static::assertSame([
            'terms' => [
                'categoryTree' => [$firstCategoryId, $secondCategoryId],
            ],
        ], $result->toArray());
    }

    public function testParseCategoriesRoIdEqualsAnyFilterCallsParentWhenOptimizationDisabled(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $filter = new EqualsAnyFilter('categoriesRo.id', [Uuid::randomHex()]);
        $expectedBuilder = static::createStub(BuilderInterface::class);

        $this->decoratedParser
            ->expects($this->once())
            ->method('parseFilter')
            ->with($filter, $this->blogDefinition, 'root', $this->context)
            ->willReturn($expectedBuilder);

        // Feature::fake([]) forces the flag off so the deprecated pre-v6.8 branch is exercised
        // regardless of the suite's ambient v6.8.0.0 state (the test runs under major too).
        $result = Feature::fake([], fn () => $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context));

        static::assertSame($expectedBuilder, $result);
    }

    public function testParseFilterCallsParentForUnhandledBlogFilter(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new BlogCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        // an Equals filter on a field other than categoriesRo.id is none of the handled blog
        // cases, so it falls through to the parent CriteriaParser instead of the decorated one
        $filter = new EqualsFilter('blogNumber', 'test');

        $this->decoratedParser->expects($this->never())->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertInstanceOf(TermQuery::class, $result);
        static::assertContains('test', $result->toArray()['term']);
    }

    private function getRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                CategoryDefinition::class,
                CategoryTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
