<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\ApiProtectionException;
use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacade;
use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Test\Script\Execution\ChannelTestHook;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class ChannelRepositoryFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private ChannelRepositoryFacadeHookFactory $factory;

    private ChannelContext $context;

    protected function setUp(): void
    {
        $this->factory = static::getContainer()->get(ChannelRepositoryFacadeHookFactory::class);
        $this->context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $this->ids = new IdsCollection();
        $this->createBlogs();
    }

    public function testEmptySearch(): void
    {
        $result = $this->createFacade()->search('blog', []);

        static::assertCount(3, $result->getEntities());
    }

    public function testSearchFilter(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'childCount', 'value' => 0],
                ],
            ]
        );

        static::assertCount(1, $result->getEntities());
        static::assertContains($this->ids->get('p3'), $result->getEntities()->getIds());
    }

    public function testSearchRead(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'ids' => [$this->ids->get('p1'), $this->ids->get('p2')],
            ]
        );

        static::assertCount(1, $result->getEntities());
        static::assertContains($this->ids->get('p1'), $result->getEntities()->getIds());
    }

    public function testSearchAggregation(): void
    {
        $result = $this->createFacade()->search(
            'blog',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'childCount'],
                ],
            ]
        );

        static::assertCount(3, $result->getEntities());
        $agg = $result->getAggregations()->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(1.0, $agg->getSum());
    }

    public function testSearchSort(): void
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

    public function testSearchIds(): void
    {
        $result = $this->createFacade()->ids('blog', []);

        static::assertCount(3, $result->getIds());
    }

    public function testSearchIdsFilter(): void
    {
        $result = $this->createFacade()->ids(
            'blog',
            [
                'filter' => [
                    ['type' => 'equals', 'field' => 'childCount', 'value' => 0],
                ],
            ]
        );

        static::assertCount(1, $result->getIds());
        static::assertContains($this->ids->get('p3'), $result->getIds());
    }

    public function testEmptyAggregation(): void
    {
        $result = $this->createFacade()->aggregate('blog', []);

        static::assertCount(0, $result);
    }

    public function testAggregation(): void
    {
        $result = $this->createFacade()->aggregate(
            'blog',
            [
                'aggregations' => [
                    ['name' => 'sum', 'type' => 'sum', 'field' => 'childCount'],
                ],
            ]
        );

        static::assertCount(1, $result);
        $agg = $result->get('sum');
        static::assertInstanceOf(SumResult::class, $agg);
        static::assertSame(1.0, $agg->getSum());
    }

    public function testIntegrationCase(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new ChannelTestHook(
            'store-search-by-id',
            $this->context,
            [
                'blogId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                ChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlog'));
        $blog = $page->getExtension('myBlog');
        static::assertInstanceOf(ChannelBlogEntity::class, $blog);
        static::assertSame($this->ids->get('p1'), $blog->getId());
    }

    public function testSearchWithFilterIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new ChannelTestHook(
            'store-filter',
            $this->context,
            [
                'page' => $page,
            ],
            [
                ChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlog'));
        $blog = $page->getExtension('myBlog');
        static::assertInstanceOf(ChannelBlogEntity::class, $blog);
        static::assertSame($this->ids->get('p1'), $blog->getId());
    }

    public function testSearchWithAssociationIntegration(): void
    {
        $this->installApp(__DIR__ . '/_fixtures/apps/pageLoadedExample');

        $page = new ArrayStruct();
        $hook = new ChannelTestHook(
            'store-association',
            $this->context,
            [
                'blogId' => $this->ids->get('p1'),
                'page' => $page,
            ],
            [
                ChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlog'));
        $blog = $page->getExtension('myBlog');
        static::assertInstanceOf(ChannelBlogEntity::class, $blog);
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
        $hook = new ChannelTestHook(
            'store-search-ids',
            $this->context,
            [
                'page' => $page,
            ],
            [
                ChannelRepositoryFacadeHookFactory::class,
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
        $hook = new ChannelTestHook(
            'store-aggregate',
            $this->context,
            [
                'page' => $page,
            ],
            [
                ChannelRepositoryFacadeHookFactory::class,
            ]
        );

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('myBlogAggregations'));
        $extension = $page->getExtension('myBlogAggregations');
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertSame(1.0, $extension->get('sum'));
    }

    public function testItThrowsForNotApiAwareField(): void
    {
        $criteria = [
            'aggregations' => [
                ['name' => 'sum', 'type' => 'sum', 'field' => 'autoIncrement'],
            ],
        ];

        static::expectException(ApiProtectionException::class);
        $this->createFacade()->search('blog', $criteria);
    }

    private function createBlogs(): void
    {
        $taxId = $this->getExistingTaxId();
        $this->ids->set('t1', $taxId);

        $blog1 = (new BlogBuilder($this->ids, 'p1'))
            ->price(100)
            ->visibility()
            ->manufacturer('m1')
            ->variant(
                (new BlogBuilder($this->ids, 'v1.1'))
                    ->build()
            );

        $blog2 = (new BlogBuilder($this->ids, 'p2'))
            ->price(200)
            ->visibility()
            ->active(false);

        $blog3 = (new BlogBuilder($this->ids, 'p3'))
            ->visibility()
            ->price(300);

        static::getContainer()->get('blog.repository')->create([
            $blog1->build(),
            $blog2->build(),
            $blog3->build(),
        ], $this->context->getContext());
    }

    private function installApp(string $appDir): string
    {
        $this->loadAppsFromDir($appDir);

        $appId = static::getContainer()->get('app.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
        static::assertIsString($appId);

        return $appId;
    }

    private function getExistingTaxId(): string
    {
        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Standard rate'));

        $taxId = $taxRepository->searchIds($criteria, $this->context->getContext())->firstId();
        static::assertIsString($taxId);

        return $taxId;
    }

    private function createFacade(): ChannelRepositoryFacade
    {
        return $this->factory->factory(
            new ChannelTestHook('test', $this->context),
            new Script('test', '', new \DateTimeImmutable())
        );
    }
}
