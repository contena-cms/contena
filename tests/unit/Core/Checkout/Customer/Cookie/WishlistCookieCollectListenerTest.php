<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Cookie\WishlistCookieCollectListener;
use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(WishlistCookieCollectListener::class)]
class WishlistCookieCollectListenerTest extends TestCase
{
    private StaticSystemConfigService $systemConfigService;

    private WishlistCookieCollectListener $listener;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService(['core.cart.wishlistEnabled' => true]);
        $this->listener = new WishlistCookieCollectListener($this->systemConfigService);
    }

    public function testWishlistConfigNotActive(): void
    {
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $cookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$cookieGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $wishlistCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES)?->getEntries()?->get('wishlist-enabled');
        static::assertNull($wishlistCookie);
    }

    public function testComfortFeaturesCookieGroupNotPresent(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([new CookieGroup('test')]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
    }

    public function testWishlistCookieIsAdded(): void
    {
        $cookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$cookieGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $wishlistCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES)?->getEntries()?->get('wishlist-enabled');
        static::assertNotNull($wishlistCookie);
    }
}
