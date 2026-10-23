<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistEntity;
use Contena\Core\System\User\Channel\AbstractAddWishlistBlogRoute;
use Contena\Core\System\User\Channel\AbstractLoadWishlistRoute;
use Contena\Core\System\User\Channel\AbstractMergeWishlistBlogRoute;
use Contena\Core\System\User\Channel\AbstractRemoveWishlistBlogRoute;
use Contena\Core\System\User\Channel\LoadWishlistRouteResponse;
use Contena\Core\System\User\Exception\UserWishlistNotFoundException;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Frontend\Controller\WishlistController;
use Contena\Frontend\Page\Wishlist\GuestWishlistPageLoader;
use Contena\Frontend\Page\Wishlist\WishListPageBlogCriteriaEvent;
use Contena\Frontend\Page\Wishlist\WishlistPageLoader;
use Contena\Frontend\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(WishlistController::class)]
class WishlistControllerTest extends TestCase
{
    private AbstractLoadWishlistRoute&MockObject $wishlistLoadRoute;

    private CollectingEventDispatcher $eventDispatcher;

    private WishlistController $controller;

    protected function setUp(): void
    {
        $this->wishlistLoadRoute = $this->createMock(AbstractLoadWishlistRoute::class);
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->controller = new WishlistController(
            static::createStub(WishlistPageLoader::class),
            $this->wishlistLoadRoute,
            static::createStub(AbstractAddWishlistBlogRoute::class),
            static::createStub(AbstractRemoveWishlistBlogRoute::class),
            static::createStub(AbstractMergeWishlistBlogRoute::class),
            static::createStub(GuestWishlistPageLoader::class),
            static::createStub(GuestWishlistPageletLoader::class),
            $this->eventDispatcher,
        );
    }

    public function testAjaxListReturnsTheWishlistBlogIds(): void
    {
        $blog = new BlogEntity();
        $blog->setId(Uuid::randomHex());

        $response = new LoadWishlistRouteResponse(
            new UserWishlistEntity(),
            new EntitySearchResult(
                'blog',
                1,
                new BlogCollection([$blog]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        );

        $this->wishlistLoadRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $result = $this->controller->ajaxList(new Request(), Generator::generateChannelContext(), new UserEntity());

        $content = $result->getContent();
        static::assertIsString($content);
        static::assertSame([$blog->getId() => $blog->getId()], json_decode($content, true));

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(WishListPageBlogCriteriaEvent::class, $events[0]);
    }

    public function testAjaxListReturnsAnEmptyListWhenTheUserHasNoWishlist(): void
    {
        $this->wishlistLoadRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException(new UserWishlistNotFoundException());

        $result = $this->controller->ajaxList(new Request(), Generator::generateChannelContext(), new UserEntity());

        $content = $result->getContent();
        static::assertIsString($content);
        static::assertSame([], json_decode($content, true));
    }
}
