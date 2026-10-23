<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoadedEvent;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed
 */
class OrderDetailPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
    }

    public function testItLoadsOrders(): void
    {
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);

        $request = new Request();
        $request->query->set('id', $orderId);

        $accountOrderDetailEvent = null;
        $this->catchEvent(AccountOrderDetailPageLoadedEvent::class, $accountOrderDetailEvent);

        $orderRequestEvent = null;
        $this->catchEvent(OrderRouteRequestEvent::class, $orderRequestEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame($orderId, $page->getOrder()->getId());
        self::assertPageEvent(AccountOrderDetailPageLoadedEvent::class, $accountOrderDetailEvent, $context, $request, $page);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $orderRequestEvent);
        static::assertSame($request, $orderRequestEvent->getFrontendRequest());
        static::assertSame($context, $orderRequestEvent->getChannelContext());
        static::assertSame($context->getContext(), $orderRequestEvent->getContext());
    }

    public function testMissingOrderIdThrowsException(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $this->expectException(RoutingException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testUnknownOrderThrowsNotFoundHttpException(): void
    {
        $request = new Request();
        $request->query->set('id', Uuid::randomHex());
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $this->expectException(NotFoundHttpException::class);
        $this->getPageLoader()->load($request, $context);
    }

    protected function getPageLoader(): AccountOrderDetailPageLoader
    {
        return static::getContainer()->get(AccountOrderDetailPageLoader::class);
    }
}
