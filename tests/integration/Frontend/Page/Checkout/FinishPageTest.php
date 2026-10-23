<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPage;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPageOrderCriteriaEvent;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class FinishPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItRequiresAOrderId(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $this->expectParamMissingException('orderId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testMissingOrderThrows(): void
    {
        $request = new Request(['orderId' => 'foo']);
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $this->expectException(OrderException::class);

        $this->getPageLoader()->load($request, $context);
    }

    public function testFinishPageLoading(): void
    {
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request = new Request(['orderId' => $orderId]);
        $criteria = new Criteria([$orderId]);

        $criteriaEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            CheckoutFinishPageOrderCriteriaEvent::class,
            static function (CheckoutFinishPageOrderCriteriaEvent $event) use (&$criteriaEvent): void {
                $criteriaEvent = $event;
            }
        );

        $event = null;
        $this->catchEvent(CheckoutFinishPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutFinishPage::class, $page);
        static::assertSame(13.04, $page->getOrder()->getPrice()->getNetPrice());
        self::assertPageEvent(CheckoutFinishPageLoadedEvent::class, $event, $context, $request, $page);
        static::assertInstanceOf(CheckoutFinishPageOrderCriteriaEvent::class, $criteriaEvent);
        static::assertSame($criteria->getIds(), $criteriaEvent->getCriteria()->getIds());
    }

    /**
     * @return CheckoutFinishPageLoader
     */
    protected function getPageLoader()
    {
        return static::getContainer()->get(CheckoutFinishPageLoader::class);
    }
}
