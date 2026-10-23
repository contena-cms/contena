<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Suggest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Filter\ManufacturerListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\PriceListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\ShippingFreeListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AggregationListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\BehaviorListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\PagingListingProcessor;
use Contena\Core\Content\Blog\Channel\Suggest\AbstractBlogSuggestRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRouteResponse;
use Contena\Core\Content\Blog\Channel\Suggest\ResolvedCriteriaBlogSuggestRoute;
use Contena\Core\Content\Blog\Events\BlogSuggestCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogSuggestResultEvent;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ResolvedCriteriaBlogSuggestRoute::class)]
class ResolvedCriteriaBlogSuggestRouteTest extends TestCase
{
    /**
     * @param array<string, mixed> $query
     * @param array<string> $expected
     */
    #[DataProvider('loadProvider')]
    public function testRequestHandling(array $query, array $expected): void
    {
        $decorated = new SuggestRouteStub();

        $route = new ResolvedCriteriaBlogSuggestRoute(
            static::createStub(BlogSearchBuilderInterface::class),
            new EventDispatcher(),
            $decorated,
            new CompositeListingProcessor([
                new PagingListingProcessor(new StaticSystemConfigService()),
                new AggregationListingProcessor(
                    [
                        new ManufacturerListingFilterHandler(),
                        new PriceListingFilterHandler(),
                        new ShippingFreeListingFilterHandler(),
                    ],
                    new EventDispatcher()
                ),
                new BehaviorListingProcessor(),
            ])
        );

        $request = new Request(array_merge(['search' => 'foo'], $query));
        $route->load($request, static::createStub(ChannelContext::class), new Criteria());

        static::assertInstanceOf(Criteria::class, $decorated->criteria);
        $fields = $decorated->criteria->getFilterFields();

        static::assertSame($expected, $fields);
    }

    public function testEvents(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

        $builder = $this->createMock(BlogSearchBuilderInterface::class);
        $builder->expects($this->once())->method('build');

        $dispatcher = new EventDispatcher();
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener(BlogSuggestCriteriaEvent::class, $listener);

        $resultListener = $this->createMock(CallableClass::class);
        $resultListener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener(BlogSuggestResultEvent::class, $resultListener);

        $context = static::createStub(ChannelContext::class);

        $route = new ResolvedCriteriaBlogSuggestRoute(
            $builder,
            $dispatcher,
            static::createStub(AbstractBlogSuggestRoute::class),
            new CompositeListingProcessor([])
        );

        $route->load($request, $context, $criteria);
    }

    public static function loadProvider(): \Generator
    {
        yield 'Test with empty request' => [
            [],
            [
                'blog.visibilities.visibility',
                'blog.visibilities.channelId',
                'blog.active',
            ],
        ];

        yield 'Test with manufacturer filter' => [
            ['manufacturer' => 'foo'],
            [
                'blog.visibilities.visibility',
                'blog.visibilities.channelId',
                'blog.active',
                'blog.manufacturerId',
            ],
        ];

        yield 'Test with min price filter' => [
            ['min-price' => 100],
            [
                'blog.visibilities.visibility',
                'blog.visibilities.channelId',
                'blog.active',
                'blog.cheapestPrice',
            ],
        ];

        yield 'Test with max price filter' => [
            ['max-price' => 100],
            [
                'blog.visibilities.visibility',
                'blog.visibilities.channelId',
                'blog.active',
                'blog.cheapestPrice',
            ],
        ];

        yield 'Test with shipping free filter' => [
            ['shipping-free' => true],
            [
                'blog.visibilities.visibility',
                'blog.visibilities.channelId',
                'blog.active',
                'blog.shippingFree',
            ],
        ];
    }
}

/**
 * @internal
 */
class SuggestRouteStub extends AbstractBlogSuggestRoute
{
    public ?Criteria $criteria = null;

    public function getDecorated(): AbstractBlogSuggestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSuggestRouteResponse
    {
        $this->criteria = $criteria;

        return new BlogSuggestRouteResponse(
            new BlogListingResult('blog', 0, new BlogCollection(), null, $criteria, Context::createDefaultContext())
        );
    }
}
