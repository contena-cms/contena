<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class SqlQueryParserTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    /**
     * @var EntityRepository<BlogManufacturerCollection>
     */
    private EntityRepository $manufacturerRepository;

    private Context $context;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->manufacturerRepository = static::getContainer()->get('blog_manufacturer.repository');

        $this->context = Context::createDefaultContext();
        $this->repository = static::getContainer()->get('blog.repository');

        $this->ids = new IdsCollection();

        $this->createBlog();

        parent::setUp();
    }

    public function testFindBlogsWithoutCategory(): void
    {
        $criteria = new Criteria([
            $this->ids->get('blog1-with-category'),
            $this->ids->get('blog2-with-category'),
            $this->ids->get('blog1-without-category'),
            $this->ids->get('blog2-without-category'),
        ]);
        $criteria->addFilter(new EqualsFilter('categoryIds', null));

        $result = $this->repository->searchIds($criteria, $this->context);

        $blogsWithoutCategory = [
            $this->ids->get('blog1-without-category'),
            $this->ids->get('blog2-without-category'),
        ];

        static::assertSame($blogsWithoutCategory, $result->getIds());
    }

    public function testFindBlogsWithCategory(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryIds', $this->ids->get('category1')));

        $result = $this->repository->searchIds($criteria, $this->context);

        $blogsWithoutCategory = [
            $this->ids->get('blog1-with-category'),
        ];

        static::assertSame($blogsWithoutCategory, $result->getIds());
    }

    #[DataProvider('whenToUseNullSafeOperatorProvider')]
    public function testWhenToUseNullSafeOperator(Filter $filter, bool $expected): void
    {
        $parser = static::getContainer()->get(SqlQueryParser::class);

        $definition = static::getContainer()->get(BlogDefinition::class);

        $parsed = $parser->parse($filter, $definition, Context::createDefaultContext(), 'blog');

        $has = false;
        foreach ($parsed->getWheres() as $where) {
            $has = $has || str_contains((string) $where, '<=>');
        }

        static::assertSame($expected, $has);
    }

    /**
     * @return iterable<array-key, array{0: Filter, 1: bool}>
     */
    public static function whenToUseNullSafeOperatorProvider()
    {
        yield 'Dont used for simple equals' => [new EqualsFilter('blog.id', Uuid::randomHex()), false];
        yield 'Used for negated comparison' => [new NandFilter([new EqualsFilter('blog.id', Uuid::randomHex())]), true];
        yield 'Used for negated null comparison' => [new NandFilter([new EqualsFilter('blog.id', null)]), true];
        yield 'Used in nested negated comparison' => [new AndFilter([new NandFilter([new EqualsFilter('blog.id', Uuid::randomHex())])]), true];
        yield 'Used for null comparison' => [new EqualsFilter('blog.id', null), true];
    }

    public function testContainsFilterFindUnderscore(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target_to_find']);
        $errournousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new ContainsFilter('link', 'target_to_find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($errournousId, $foundIds->getIds());
    }

    public function testContainsFilterFindPercentageSign(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target%find']);
        $errournousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new ContainsFilter('link', 'target%find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($errournousId, $foundIds->getIds());
    }

    public function testContainsFilterFindBackslash(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target \\ find']);
        $errournousId = $this->createManufacturer(['link' => 'target \\find']);
        $criteria = (new Criteria())->addFilter(new ContainsFilter('link', ' \\ '));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($errournousId, $foundIds->getIds());
    }

    public function testPrefixFilterFindUnderscore(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target_to_find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new PrefixFilter('link', 'target_to'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testPrefixFilterFindPercentageSign(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target%find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new PrefixFilter('link', 'target%fi'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testPrefixFilterFindBackslash(): void
    {
        $targetId = $this->createManufacturer(['link' => '\\ target find']);
        $erroneousId = $this->createManufacturer(['link' => '\\target find']);
        $criteria = (new Criteria())->addFilter(new PrefixFilter('link', '\\ '));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testSuffixFilterFindUnderscore(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target_to_find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new SuffixFilter('link', 'to_find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testSuffixFilterFindPercentageSign(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target%find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new SuffixFilter('link', 'et%find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testSuffixFilterFindBackslash(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target find \\']);
        $erroneousId = $this->createManufacturer(['link' => 'target find\\']);
        $criteria = (new Criteria())->addFilter(new SuffixFilter('link', ' \\'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testEqualsAnyFilter(): void
    {
        $testIds = [
            'nullLink' => $this->createManufacturer([]), // null link
            'specialLink1' => $this->createManufacturer(['link' => 'specialLink1']),
            'specialLink2' => $this->createManufacturer(['link' => 'specialLink2']),
        ];

        // empty array
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('link', []));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        static::assertCount(0, array_intersect($foundIds, $testIds));

        // string scenario
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('link', ['specialLink2']));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        static::assertCount(1, array_intersect($foundIds, $testIds));
        static::assertContains($testIds['specialLink2'], $foundIds);

        // null scenario
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('link', [null]));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        static::assertCount(1, array_intersect($foundIds, $testIds));
        static::assertContains($testIds['nullLink'], $foundIds);

        // combined scenario
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('link', [null, 'specialLink1']));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        static::assertCount(2, array_intersect($foundIds, $testIds));
        static::assertContains($testIds['nullLink'], $foundIds);
        static::assertContains($testIds['specialLink1'], $foundIds);
    }

    public function testNegatedEqualsAnyFilterKeepsBlogsWithNullManufacturer(): void
    {
        $ids = new IdsCollection();

        $this->repository->create([
            (new BlogBuilder($ids, 'blog-with-blocked-manufacturer'))
                ->manufacturer('blocked-manufacturer')
                ->price(10)
                ->build(),
            (new BlogBuilder($ids, 'blog-with-other-manufacturer'))
                ->manufacturer('other-manufacturer')
                ->price(10)
                ->build(),
            (new BlogBuilder($ids, 'blog-without-manufacturer'))
                ->price(10)
                ->build(),
        ], $this->context);

        $criteria = new Criteria($ids->getList([
            'blog-with-blocked-manufacturer',
            'blog-with-other-manufacturer',
            'blog-without-manufacturer',
        ]));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsAnyFilter('manufacturerId', [$ids->get('blocked-manufacturer')]),
        ]));

        $foundIds = $this->repository->searchIds($criteria, $this->context)->getIds();

        static::assertEqualsCanonicalizing([
            $ids->get('blog-with-other-manufacturer'),
            $ids->get('blog-without-manufacturer'),
        ], array_values($foundIds));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createManufacturer(array $parameters = []): string
    {
        $id = Uuid::randomHex();

        $defaults = ['id' => $id, 'name' => 'Test'];

        $parameters = array_merge($defaults, $parameters);

        $this->manufacturerRepository->create([$parameters], Context::createDefaultContext());

        return $id;
    }

    private function createBlog(): void
    {
        $blogs = [
            (new BlogBuilder($this->ids, 'blog1-with-category', 10))
                ->categories(['category1', 'category2'])
                ->visibility()
                ->price(10)
                ->build(),
            (new BlogBuilder($this->ids, 'blog2-with-category', 12))
                ->category('category2')
                ->visibility()
                ->price(20)
                ->build(),
            (new BlogBuilder($this->ids, 'blog1-without-category', 14))
                ->visibility()
                ->price(30)
                ->build(),
            (new BlogBuilder($this->ids, 'blog2-without-category', 16))
                ->visibility()
                ->price(40)
                ->build(),
        ];

        $this->repository->create($blogs, Context::createDefaultContext());
    }
}
