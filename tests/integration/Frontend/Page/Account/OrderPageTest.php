<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Page\Account\Order\AccountOrderPageLoadedEvent;
use Contena\Frontend\Page\Account\Order\AccountOrderPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class OrderPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItLoadsOrders(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $event = null;
        $this->catchEvent(AccountOrderPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(0, $page->getOrders()->getEntities());
        self::assertPageEvent(AccountOrderPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testChannelRestriction(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();
        $testContext = $this->createChannelContext();

        $this->placeRandomOrder($context);
        $order = $this->placeRandomOrder($context);
        static::getContainer()->get('order.repository')->update([
            [
                'id' => $order,
                'channelId' => $testContext->getChannelId(),
            ],
        ], $context->getContext());

        $event = null;
        $this->catchEvent(AccountOrderPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(1, $page->getOrders()->getEntities());
        self::assertPageEvent(AccountOrderPageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): AccountOrderPageLoader
    {
        return static::getContainer()->get(AccountOrderPageLoader::class);
    }
}
