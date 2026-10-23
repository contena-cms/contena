<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptAppInformation;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Test\Script\Execution\TestHook;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class RepositoryFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private RepositoryFacadeHookFactory $factory;

    protected function setUp(): void
    {
        $this->factory = static::getContainer()->get(RepositoryFacadeHookFactory::class);
        $this->ids = new IdsCollection();
        $this->createBlogs();
    }

    public function testEmptySearchWithoutApp(): void
    {
        $result = $this->createFacade()->search('blog', []);

        static::assertCount(4, $result->getEntities());
        static::assertCount(4, $result->getEntities()->getIds());
    }

    public function testSearchFilterWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                ],
            ]
        );

        static::assertCount(3, $result->getEntities());
        static::assertNotContains($this->ids->get('p2'), $result->getEntities()->getIds());
    }

    public function testSearchReadWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'ids' => [$this->ids->get('p1'), $this->ids->get('p2')],
            ]
        );

        static::assertCount(2, $result->getEntities());
        static::assertContains($this->ids->get('p1'), $result->getEntities()->getIds());
        static::assertContains($this->ids->get('p2'), $result->getEntities()->getIds());
    }

    public function testSearchAggregationWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
                ],
            ]
        );

        static::assertCount(4, $result->getEntities());
        $agg = $result->getAggregations()->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(600.0, $agg->getSum());
    }

    public function testSearchSortWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'sort' => [['field' => 'id']],
            ]
        );

        $actual = $result->getEntities()->getIds();
        $expected = $actual;
        sort($expected);

        static::assertSame($expected, array_values($actual));
    }

    public function testEmptySearchIdsWithoutApp(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'ids' => [$this->ids->get('p1'), $this->ids->get('p2')],
            ]
        );

        static::assertCount(2, $result->getEntities());
        static::assertContains($this->ids->get('p1'), $result->getEntities()->getIds());
        static::assertContains($this->ids->get('p2'), $result->getEntities()->getIds());
    }

    public function testEmptyIdsWithoutApp(): void
    {
        $result = $this->createFacade()->ids('blog', []);

        static::assertCount(4, $result->getIds());
    }

    public function testIdsFilterWithoutApp(): void
    {
        $result = $this->createFacade()->ids(
            'blog',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                ],
            ]
        );

        static::assertCount(3, $result->getIds());
        static::assertNotContains($this->ids->get('p2'), $result->getIds());
    }

    public function testEmptyAggregationWithoutApp(): void
    {
        $result = $this->createFacade()->aggregate('blog', []);

        static::assertCount(0, $result);
    }

    public function testAggregationWithoutApp(): void
    {
        $result = $this->createFacade()->aggregate(
            'blog',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
                ],
            ]
        );

        static::assertCount(1, $result);
        $agg = $result->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(600.0, $agg->getSum());
    }

    public function testWithApp(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withBlogPermission')
        );

        $result = $facade->search('blog', []);
        static::assertCount(4, $result->getEntities());

        $result = $facade->ids('blog', []);
        static::assertCount(4, $result->getIds());

        $result = $facade->aggregate('blog', [
            'aggregations' => [
                ['name' => 'sum', 'type' => 'sum', 'field' => 'price.gross'],
            ],
        ]);
        $agg = $result->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(600.0, $agg->getSum());
    }

    public function testSearchWithAppWithoutNeededPermissions(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withoutBlogPermission')
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->search('blog', []);
    }

    public function testIdsWithAppWithoutNeededPermissions(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withoutBlogPermission')
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->ids('blog', []);
    }

    public function testAggregateIdsWithAppWithoutNeededPermissions(): void
    {
        $facade = $this->createFacade(
            $this->installApp(__DIR__ . '/_fixtures/apps/withoutBlogPermission')
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->aggregate('blog', []);
    }

    public function testSearchByIdIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-search-by-id',
            Context::createDefaultContext(),
            [
                'blogId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlog'));
        $blog = $page->getExtension('myBlog');
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame($this->ids->get('p1'), $blog->getId());
    }

    public function testSearchWithFilterIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-filter',
            Context::createDefaultContext(),
            [
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlog'));
        $blog = $page->getExtension('myBlog');
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame($this->ids->get('p1'), $blog->getId());
    }

    public function testSearchWithAssociationIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-association',
            Context::createDefaultContext(),
            [
                'blogId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlog'));
        $blog = $page->getExtension('myBlog');
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame($this->ids->get('p1'), $blog->getId());
        static::assertInstanceOf(BlogManufacturerEntity::class, $blog->getManufacturer());

        $manufacturer = $page->getExtension('myManufacturer');
        static::assertInstanceOf(BlogManufacturerEntity::class, $manufacturer);
        static::assertSame($this->ids->get('m1'), $manufacturer->getId());
    }

    public function testSearchIdsIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-search-ids',
            Context::createDefaultContext(),
            [
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlogIds'));
        $extension = $page->getExtension('myBlogIds');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame([$this->ids->get('p1')], $extension->get('ids'));
    }

    public function testAggregateIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'repository-aggregate',
            Context::createDefaultContext(),
            [
                'page' => $page,
            ],
            [
                RepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlogAggregations'));
        $extension = $page->getExtension('myBlogAggregations');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(600.0, $extension->get('sum'));
    }

    private function createBlogs(): void
    {
        $blog1 = (new BlogBuilder($this->ids, 'p1'))
            ->price(100)
            ->manufacturer('m1')
            ->variant(
                (new BlogBuilder($this->ids, 'v1.1'))
                ->build()
            );

        $blog2 = (new BlogBuilder($this->ids, 'p2'))
            ->price(200)
            ->active(false);

        $blog3 = (new BlogBuilder($this->ids, 'p3'))
            ->price(300);

        static::getContainer()->get('blog.repository')->create([
            $blog1->build(),
            $blog2->build(),
            $blog3->build(),
        ], Context::createDefaultContext());
    }

    private function installApp(string $appDir): ScriptAppInformation
    {
        $this->loadAppsFromDir($appDir);

        /** @var EntityRepository<AppCollection> $repository */
        $repository = static::getContainer()->get('app.repository');
        $app = $repository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($app);

        return new ScriptAppInformation(
            $app->getId(),
            $app->getName(),
            $app->getVersion(),
            $app->getIntegrationId()
        );
    }

    private function createFacade(?ScriptAppInformation $scriptAppInformation = null): RepositoryFacade
    {
        return $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $scriptAppInformation)
        );
    }
}
