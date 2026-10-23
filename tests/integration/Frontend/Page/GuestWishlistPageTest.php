<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Page\Wishlist\GuestWishlistPageLoadedEvent;
use Contena\Frontend\Page\Wishlist\GuestWishlistPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class GuestWishlistPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItLoadsWishlistGuestPage(): void
    {
        $request = new Request();
        $context = $this->createChannelContext();

        $event = null;
        $this->catchEvent(GuestWishlistPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(GuestWishlistPageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): GuestWishlistPageLoader
    {
        return static::getContainer()->get(GuestWishlistPageLoader::class);
    }
}
