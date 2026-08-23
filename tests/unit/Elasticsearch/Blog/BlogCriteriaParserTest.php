<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Elasticsearch\Blog\BlogCriteriaParser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(BlogCriteriaParser::class)]
class BlogCriteriaParserTest extends TestCase
{
    private BlogCriteriaParser $parser;

    private EntityDefinition $blogDefinition;

    private EntityDefinition $categoryDefinition;

    private Context $context;

    protected function setUp(): void
    {
        $registry = $this->getRegistry();
        $this->parser = new BlogCriteriaParser(
            new EntityDefinitionQueryHelper(),
            static::createStub(CustomFieldService::class),
        );
        $this->blogDefinition = $registry->getByEntityName(BlogDefinition::ENTITY_NAME);
        $this->categoryDefinition = $registry->getByEntityName(CategoryDefinition::ENTITY_NAME);
        $this->context = new Context(new SystemSource());
    }

    public function testParseFilterWithNonBlogDefinition(): void
    {
        $result = $this->parser->parseFilter(
            new EqualsFilter('name', 'test'),
            $this->categoryDefinition,
            'root',
            $this->context,
        );

        static::assertInstanceOf(TermQuery::class, $result);
        static::assertSame([
            'term' => [
                'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'test',
            ],
        ], $result->toArray());
    }

    public function testParseBlogAvailableFilter(): void
    {
        $channelId = Uuid::randomHex();
        $visibility = 30;

        $result = $this->parser->parseFilter(
            new BlogAvailableFilter($channelId, $visibility),
            $this->blogDefinition,
            'root',
            $this->context,
        );

        static::assertInstanceOf(BoolQuery::class, $result);
        static::assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['active' => true]],
                    ['range' => ['visibility_' . $channelId => ['gte' => $visibility]]],
                ],
            ],
        ], $result->toArray());
    }

    public function testParseBlogAvailableFilterWithoutActiveFilter(): void
    {
        $channelId = Uuid::randomHex();
        $visibility = 30;
        $filter = new BlogAvailableFilter($channelId, $visibility);
        $queries = $filter->getQueries();
        array_pop($queries);
        $filter->assign(['queries' => $queries]);

        $result = $this->parser->parseFilter($filter, $this->blogDefinition, 'root', $this->context);

        static::assertSame([
            'range' => [
                'visibility_' . $channelId => ['gte' => $visibility],
            ],
        ], $result->toArray());
    }

    public function testParseCategoriesRoIdEqualsFilter(): void
    {
        $categoryId = Uuid::randomHex();

        $result = $this->parser->parseFilter(
            new EqualsFilter('categoriesRo.id', $categoryId),
            $this->blogDefinition,
            'root',
            $this->context,
        );

        static::assertInstanceOf(TermQuery::class, $result);
        static::assertSame(['term' => ['categoryTree' => $categoryId]], $result->toArray());
    }

    public function testParseCategoriesRoIdEqualsFilterWithNullValue(): void
    {
        $result = $this->parser->parseFilter(
            new EqualsFilter('categoriesRo.id', null),
            $this->blogDefinition,
            'root',
            $this->context,
        );

        static::assertInstanceOf(BoolQuery::class, $result);
        static::assertSame([
            'bool' => [
                'must_not' => [
                    ['exists' => ['field' => 'categoryTree']],
                ],
            ],
        ], $result->toArray());
    }

    public function testParseCategoriesRoIdEqualsAnyFilter(): void
    {
        $categoryIds = [Uuid::randomHex(), Uuid::randomHex()];

        $result = $this->parser->parseFilter(
            new EqualsAnyFilter('categoriesRo.id', $categoryIds),
            $this->blogDefinition,
            'root',
            $this->context,
        );

        static::assertInstanceOf(TermsQuery::class, $result);
        static::assertSame(['terms' => ['categoryTree' => $categoryIds]], $result->toArray());
    }

    public function testParseFilterCallsParentForUnhandledBlogFilter(): void
    {
        $result = $this->parser->parseFilter(
            new EqualsFilter('active', true),
            $this->blogDefinition,
            'root',
            $this->context,
        );

        static::assertInstanceOf(TermQuery::class, $result);
        static::assertSame(['term' => ['active' => true]], $result->toArray());
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
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }
}
