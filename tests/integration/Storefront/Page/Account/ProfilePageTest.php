<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class ProfilePageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsTheProfilePage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $event = null;
        $this->catchEvent(AccountProfilePageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(AccountProfilePageLoadedEvent::class, $event, $context, $request, $page);
    }

}
