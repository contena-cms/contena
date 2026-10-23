<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Error\ErrorCollection;
use Contena\Core\Checkout\Gateway\Channel\CheckoutGatewayRouteResponse;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartGatewayResult;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CartPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItLoadsTheCart(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(CheckoutCartPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        self::assertPageEvent(CheckoutCartPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testAddsCurrentSelectedShippingMethod(): void
    {
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $context = $this->createChannelContextWithNavigation();

        $cartService = static::createStub(FrontendCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn(new FrontendCartGatewayResult(new Cart($context->getToken()), $response));

        $loader = new CheckoutCartPageLoader(
            static::getContainer()->get(GenericPageLoader::class),
            static::getContainer()->get('event_dispatcher'),
            $cartService,
            static::getContainer()->get(CountryRoute::class),
            static::getContainer()->get(Translator::class)
        );

        $result = $loader->load(new Request(), $context);

        static::assertTrue($result->getShippingMethods()->has($context->getShippingMethod()->getId()));
    }

    protected function getPageLoader(): CheckoutCartPageLoader
    {
        return static::getContainer()->get(CheckoutCartPageLoader::class);
    }
}
