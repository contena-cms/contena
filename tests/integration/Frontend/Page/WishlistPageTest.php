<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Page\PageLoadedEvent;
use Contena\Frontend\Page\Wishlist\WishlistPageLoadedEvent;
use Contena\Frontend\Page\Wishlist\WishlistPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class WishlistPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testInActiveWishlist(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);

        $this->expectExceptionObject(UserException::userWishlistNotActivated());

        $this->getPageLoader()->load($request, $context, $user);
    }

    public function testWishlistNotFound(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $page = $this->getPageLoader()->load($request, $context, $this->createUser());

        static::assertSame(0, $page->getWishlist()->getBlogListing()->getTotal());
    }

    public function testItLoadsWishlistPage(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $blog = $this->getRandomBlog($context);
        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        $this->createUserWishlist($user->getId(), $blog->getId(), $context->getChannelId());

        $event = null;
        $this->catchEvent(WishlistPageLoadedEvent::class, $event);
        $page = $this->getPageLoader()->load($request, $context, $user);

        static::assertInstanceOf(PageLoadedEvent::class, $event);

        static::assertSame(1, $page->getWishlist()->getBlogListing()->getTotal());
        static::assertSame($context->getContext(), $page->getWishlist()->getBlogListing()->getContext());
        self::assertPageEvent(WishlistPageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): WishlistPageLoader
    {
        return static::getContainer()->get(WishlistPageLoader::class);
    }

    private function createUserWishlist(string $userId, string $blogId, string $channelId): string
    {
        $userWishlistId = Uuid::randomHex();
        $userWishlistRepository = static::getContainer()->get('user_wishlist.repository');

        $userWishlistRepository->create([
            [
                'id' => $userWishlistId,
                'userId' => $userId,
                'channelId' => $channelId,
                'blogs' => [
                    [
                        'blogId' => $blogId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $userWishlistId;
    }
}
