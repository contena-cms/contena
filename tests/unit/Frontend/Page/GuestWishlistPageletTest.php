<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Content\Blog\Channel\BlogListRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Frontend\Pagelet\Wishlist\GuestWishListPageletBlogCriteriaEvent;
use Contena\Frontend\Pagelet\Wishlist\GuestWishlistPageletLoadedEvent;
use Contena\Frontend\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(GuestWishlistPageletLoader::class)]
class GuestWishlistPageletTest extends TestCase
{
    use EventDispatcherBehaviour;

    private BlogListRoute&Stub $blogListRouteMock;

    private SystemConfigService $systemConfigServiceStub;

    private ChannelContext $channelContextMock;

    private EventDispatcher $eventDispatcher;

    private AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory;

    protected function setUp(): void
    {
        $this->blogListRouteMock = static::createStub(BlogListRoute::class);
        $this->systemConfigServiceStub = new StaticSystemConfigService([
            'core.listing.hideCloseoutBlogsWhenOutOfStock' => true,
        ]);
        $this->channelContextMock = static::createStub(ChannelContext::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->blogCloseoutFilterFactory = new BlogCloseoutFilterFactory();
    }

    public function testItThrowsExceptionWithInvalidBlogIds(): void
    {
        $this->expectException(RoutingException::class);

        $request = new Request();

        $request->request->set('blogIds', 'invalid value');

        $this->getPageLoader()->load($request, $this->channelContextMock);
    }

    public function testItLoadsFilledPageletAndThrowsEvent(): void
    {
        $request = new Request();

        $request->request->set('blogIds', [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()]);

        // Mocks the load function
        $blogRouteLoadClosure = static function (Criteria $criteria, ChannelContext $context): BlogListResponse {
            $blog1 = new BlogEntity();
            static::assertIsString($criteria->getIds()[0]);
            $blog1->setUniqueIdentifier($criteria->getIds()[0]);
            $blog2 = new BlogEntity();
            static::assertIsString($criteria->getIds()[1]);
            $blog2->setUniqueIdentifier($criteria->getIds()[1]);
            $blog3 = new BlogEntity();
            static::assertIsString($criteria->getIds()[2]);
            $blog3->setUniqueIdentifier($criteria->getIds()[2]);
            $searchResult = new EntitySearchResult(
                'blog',
                3,
                new BlogCollection([$blog1, $blog2, $blog3]),
                null,
                $criteria,
                $context->getContext()
            );

            return new BlogListResponse($searchResult);
        };

        $blogListRoute = $this->createMock(BlogListRoute::class);
        $blogListRoute->expects($this->once())->method('load')->willReturnCallback($blogRouteLoadClosure);

        $context = $this->channelContextMock;

        $eventDidRun = null;
        $listenerClosure = static function (GuestWishlistPageletLoadedEvent $event) use (
            &$eventDidRun,
            $context,
            $request
        ): void {
            $eventDidRun = true;
            static::assertSame($context, $event->getChannelContext());
            static::assertSame($request, $event->getRequest());
            static::assertCount(3, $event->getPagelet()->getSearchResult()->getBlogs());
        };

        $this->addEventListener($this->eventDispatcher, GuestWishlistPageletLoadedEvent::class, $listenerClosure);

        $page = $this->getPageLoader($blogListRoute)->load($request, $context);

        static::assertCount(3, $page->getSearchResult()->getBlogs());
        static::assertTrue($eventDidRun);
    }

    public function testCriteria(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request();
        $request->request->set('blogIds', [$blogId]);

        $context = $this->channelContextMock;

        $eventDidRun = null;
        $listenerClosure = function (GuestWishListPageletBlogCriteriaEvent $event) use (
            &$eventDidRun,
            $blogId,
            $context
        ): void {
            $eventDidRun = true;
            $expectedCriteria = new Criteria();
            $expectedCriteria->setLimit(100);
            $expectedCriteria->setIds([$blogId]);
            $expectedCriteria->addAssociation('manufacturer')
                ->addAssociation('options.group')
                ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

            $filter = $this->blogCloseoutFilterFactory->create($context);
            $expectedCriteria->addFilter($filter);

            static::assertEquals($expectedCriteria, $event->getCriteria());
        };

        $this->addEventListener($this->eventDispatcher, GuestWishListPageletBlogCriteriaEvent::class, $listenerClosure);

        $this->getPageLoader()->load($request, $context);

        static::assertTrue($eventDidRun);
    }

    public function testItLoadsEmptyPagelet(): void
    {
        $request = new Request();

        $request->attributes->set('blogIds', []);

        $page = $this->getPageLoader()->load($request, $this->channelContextMock);

        static::assertCount(0, $page->getSearchResult()->getBlogs());
    }

    private function getPageLoader(?BlogListRoute $blogListRoute = null): GuestWishlistPageletLoader
    {
        return new GuestWishlistPageletLoader(
            $blogListRoute ?? $this->blogListRouteMock,
            $this->systemConfigServiceStub,
            $this->eventDispatcher,
            $this->blogCloseoutFilterFactory
        );
    }
}
