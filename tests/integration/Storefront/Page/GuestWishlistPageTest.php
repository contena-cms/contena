<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Wishlist\GuestWishlistPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class GuestWishlistPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsWishlistGuestPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContext();

        $event = null;
        $this->catchEvent(GuestWishlistPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(GuestWishlistPageLoadedEvent::class, $event, $context, $request, $page);
    }

}
