<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Checkout\Cart\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\AbstractCartPersister;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\Error\ErrorCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Gateway\Channel\AbstractCheckoutGatewayRoute;
use Contena\Core\Checkout\Gateway\Channel\CheckoutGatewayRouteResponse;
use Contena\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Payment\Channel\PaymentMethodRoute;
use Contena\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Contena\Core\Checkout\Shipping\Channel\ShippingMethodRoute;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Checkout\Cart\Error\PaymentMethodChangedError;
use Contena\Frontend\Checkout\Cart\Error\ShippingMethodChangedError;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Contena\Frontend\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FrontendCartFacade::class)]
class FrontendCartFacadeTest extends TestCase
{
    public function testGetNoBlockedMethods(): void
    {
        $cart = $this->getCart();
        $cart->setErrors($this->getCartErrorCollection());

        $cartFacade = $this->getFrontendCartFacade($cart);
        $channelContext = $this->getChannelContext();
        $returnedCart = $cartFacade->get('', $channelContext);

        static::assertEquals($this->getCart(), $returnedCart);
    }

    public function testGetBlockedShippingMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $channelContext = $this->getChannelContextMock();
        $channelContext
            ->expects($this->exactly(2))
            ->method('assign')
            ->willReturnCallback(
                static function ($newMethods) use ($channelContext): void {
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());

                    $channelContext->getShippingMethod()->setName($shippingMethod->getName());
                }
            );

        $cartFacade = $this->getFrontendCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $channelContext);

        $filtered = $errorCollection->filterInstance(ShippingMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'fallback-shipping-method-id',
            'newShippingMethodName' => 'fallback-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true));
        static::assertNotSame($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(false, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $channelContext = $this->getChannelContextMock();
        $channelContext
            ->expects($this->exactly(2))
            ->method('assign')
            ->willReturnCallback(
                static function ($newMethods) use ($channelContext): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());

                    $channelContext->getPaymentMethod()->setName($paymentMethod->getName());
                }
            );

        $cartFacade = $this->getFrontendCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnOriginalMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $channelContext);

        $filtered = $errorCollection->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'oldPaymentMethodId' => 'original-payment-method-id',
            'oldPaymentMethodName' => 'original-payment-method-name',
            'newPaymentMethodId' => 'fallback-payment-method-id',
            'newPaymentMethodName' => 'fallback-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(false, true));
        static::assertNotSame($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentAndShippingMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $channelContext = $this->getChannelContextMock();
        $channelContext
            ->expects($this->exactly(2))
            ->method('assign')
            ->willReturnCallback(
                static function ($newMethods) use ($channelContext): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());

                    $channelContext->getPaymentMethod()->setName($paymentMethod->getName());
                    $channelContext->getShippingMethod()->setName($shippingMethod->getName());
                }
            );

        $cartFacade = $this->getFrontendCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $channelContext);

        $filtered = $errorCollection->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'oldPaymentMethodId' => 'original-payment-method-id',
            'oldPaymentMethodName' => 'original-payment-method-name',
            'newPaymentMethodId' => 'fallback-payment-method-id',
            'newPaymentMethodName' => 'fallback-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $filtered = $errorCollection->filterInstance(ShippingMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'fallback-shipping-method-id',
            'newShippingMethodName' => 'fallback-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true, true));
        static::assertNotSame($controlCart, $returnedCart);
    }

    public function testGetBlockedShippingMethodNoFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $channelContext = $this->getChannelContextMock();
        $channelContext
            ->expects($this->never())
            ->method('assign')
            ->willReturnCallback(
                static function ($newMethods): void {
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('original-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getFrontendCartFacade($cart);
        $returnedCart = $cartFacade->get('', $channelContext);

        static::assertCount(0, $errorCollection->filterInstance(ShippingMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(ShippingMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getShippingMethodId(),
            'name' => 'original-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentMethodNoFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(false, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $channelContext = $this->getChannelContextMock();
        $channelContext
            ->expects($this->never())
            ->method('assign')
            ->willReturnCallback(
                static function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('original-payment-method-name', $paymentMethod->getName());
                }
            );

        $cartFacade = $this->getFrontendCartFacade($cart);
        $returnedCart = $cartFacade->get('', $channelContext);

        static::assertCount(0, $errorCollection->filterInstance(PaymentMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(PaymentMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getPaymentMethodId(),
            'name' => 'original-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(false, true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentAndShippingMethodWillUpdateOriginalContext(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('original-shipping-method-id');
        $shippingMethod->setName('original-shipping-method-name');

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('original-payment-method-id');
        $paymentMethod->setName('original-payment-method-name');

        $channelContext = Generator::generateChannelContext(paymentMethod: $paymentMethod, shippingMethod: $shippingMethod);

        $ruleIds = ['id'];
        $areaRuleIds = ['area' => ['id']];

        $cartCalculator = static::createStub(CartCalculator::class);
        $cartCalculator
            ->method('calculate')
            ->willReturnCallback(static function (Cart $cart, ChannelContext $context) use ($ruleIds, $areaRuleIds): Cart {
                $context->setRuleIds($ruleIds);
                $context->setAreaRuleIds($areaRuleIds);

                return $cart;
            });

        $cartFacade = $this->getFrontendCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...),
            $cartCalculator
        );

        $cartFacade->get('', $channelContext);

        static::assertSame('fallback-payment-method-name', $channelContext->getPaymentMethod()->getName());
        static::assertSame('fallback-shipping-method-name', $channelContext->getShippingMethod()->getName());
        static::assertSame($ruleIds, $channelContext->getRuleIds());
        static::assertSame($areaRuleIds, $channelContext->getAreaRuleIds());
    }

    public function testGetUnswitchableCart(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $channelContext = $this->getChannelContext();
        $channelContext
            ->method('assign')
            ->willReturnCallback(
                static function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());

                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getFrontendCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherUnswitchableCart(...),
            $this->callbackPaymentMethodSwitcherUnswitchableCart(...)
        );
        $returnedCart = $cartFacade->get('', $channelContext);

        static::assertCount(0, $errorCollection->filterInstance(ShippingMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(ShippingMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getShippingMethodId(),
            'name' => 'original-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        static::assertCount(0, $errorCollection->filterInstance(PaymentMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(PaymentMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getPaymentMethodId(),
            'name' => 'original-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true, true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testCartServiceIsCalledTaxedAndWithNoCaching(): void
    {
        $cartService = static::createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with(
                'token',
                static::isInstanceOf(ChannelContext::class),
                false,
                true
            );

        $cartFacade = new FrontendCartFacade(
            $cartService,
            static::createStub(BlockedShippingMethodSwitcher::class),
            static::createStub(BlockedPaymentMethodSwitcher::class),
            static::createStub(ContextSwitchRoute::class),
            static::createStub(CartCalculator::class),
            static::createStub(AbstractCartPersister::class),
            static::createStub(AbstractCheckoutGatewayRoute::class),
        );

        $cartFacade->get(
            'token',
            static::createStub(ChannelContext::class),
            false,
            true
        );
    }

    public function testGetWithCheckoutGatewaySwitchesBlockedPaymentMethodToGatewayDefault(): void
    {
        $cart = $this->getCart();
        $paymentMethods = new PaymentMethodCollection([
            $this->createPaymentMethod('gateway-default-payment-method-id', 'Gateway default payment method'),
            $this->createPaymentMethod('gateway-other-payment-method-id', 'Gateway other payment method'),
        ]);
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $loadedCart, ChannelContext $context) use ($paymentMethods, $shippingMethods): CheckoutGatewayRouteResponse {
                $loadedCart->getErrors()->add(new PaymentMethodBlockedError(
                    id: 'original-payment-method-id',
                    name: 'original-payment-method-name',
                    reason: 'not allowed',
                ));

                return new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $loadedCart->getErrors());
            });

        $channelContext = $this->getChannelContextWithDefaults(
            defaultPaymentMethodId: 'gateway-default-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getFrontendCartFacade(
            cart: $cart,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $channelContext);

        static::assertSame($cart, $result->cart);
        static::assertSame($paymentMethods, $result->gatewayResponse->getPaymentMethods());
        static::assertSame('gateway-default-payment-method-id', $channelContext->getPaymentMethod()->getId());

        $errors = $result->cart->getErrors();
        static::assertCount(0, $errors->filterInstance(PaymentMethodBlockedError::class));
        $changedErrors = $errors->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $changedErrors);
        $changedError = $changedErrors->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $changedError);
        static::assertSame('original-payment-method-id', $changedError->getOldPaymentMethodId());
        static::assertSame('gateway-default-payment-method-id', $changedError->getNewPaymentMethodId());
    }

    public function testGetWithCheckoutGatewaySwitchesBlockedPaymentMethodToFirstGatewayMethodWhenDefaultIsUnavailable(): void
    {
        $cart = $this->getCart();
        $firstGatewayMethod = $this->createPaymentMethod('gateway-first-payment-method-id', 'Gateway first payment method');
        $paymentMethods = new PaymentMethodCollection([
            $firstGatewayMethod,
            $this->createPaymentMethod('gateway-second-payment-method-id', 'Gateway second payment method'),
        ]);
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $loadedCart, ChannelContext $context) use ($paymentMethods, $shippingMethods): CheckoutGatewayRouteResponse {
                $loadedCart->getErrors()->add(new PaymentMethodBlockedError(
                    id: 'original-payment-method-id',
                    name: 'original-payment-method-name',
                    reason: 'not allowed',
                ));

                return new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $loadedCart->getErrors());
            });

        $channelContext = $this->getChannelContextWithDefaults(
            defaultPaymentMethodId: 'unavailable-default-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getFrontendCartFacade(
            cart: $cart,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $channelContext);

        static::assertSame($cart, $result->cart);
        static::assertSame($firstGatewayMethod->getId(), $channelContext->getPaymentMethod()->getId());
    }

    public function testGetWithCheckoutGatewayKeepsBlockedPaymentMethodWhenGatewayHasNoFallback(): void
    {
        $cart = $this->getCart();
        $paymentMethods = new PaymentMethodCollection();
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $loadedCart, ChannelContext $context) use ($paymentMethods, $shippingMethods): CheckoutGatewayRouteResponse {
                $loadedCart->getErrors()->add(new PaymentMethodBlockedError(
                    id: 'original-payment-method-id',
                    name: 'original-payment-method-name',
                    reason: 'not allowed',
                ));

                return new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $loadedCart->getErrors());
            });

        $channelContext = $this->getChannelContextWithDefaults(
            defaultPaymentMethodId: 'gateway-default-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getFrontendCartFacade(
            cart: $cart,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $channelContext);

        static::assertSame($cart, $result->cart);
        static::assertSame('original-payment-method-id', $channelContext->getPaymentMethod()->getId());
        static::assertCount(1, $result->cart->getErrors()->filterInstance(PaymentMethodBlockedError::class));
        static::assertCount(0, $result->cart->getErrors()->filterInstance(PaymentMethodChangedError::class));
    }

    public function testGetWithCheckoutGatewayReturnsGatewayResponseWithoutSwitchWhenNoMethodIsBlocked(): void
    {
        $cart = $this->getCart();
        $paymentMethods = new PaymentMethodCollection([
            $this->createPaymentMethod('original-payment-method-id', 'Original payment method'),
        ]);
        $shippingMethods = new ShippingMethodCollection([
            $this->createShippingMethod('original-shipping-method-id', 'Original shipping method'),
        ]);

        $response = new CheckoutGatewayRouteResponse($paymentMethods, $shippingMethods, $cart->getErrors());

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator
            ->expects($this->never())
            ->method('calculate');

        $channelContext = $this->getChannelContextWithDefaults(
            defaultPaymentMethodId: 'original-payment-method-id',
            defaultShippingMethodId: 'original-shipping-method-id',
        );

        $cartFacade = $this->getFrontendCartFacade(
            cart: $cart,
            cartCalculator: $cartCalculator,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), 'token', $channelContext);

        static::assertSame($cart, $result->cart);
        static::assertSame($response, $result->gatewayResponse);
        static::assertSame('original-payment-method-id', $channelContext->getPaymentMethod()->getId());
    }

    public function callbackShippingMethodSwitcherReturnOriginalMethod(ErrorCollection $errors, ChannelContext $channelContext): ShippingMethodEntity
    {
        return $channelContext->getShippingMethod();
    }

    public function callbackShippingMethodSwitcherReturnFallbackMethod(ErrorCollection $errors, ChannelContext $channelContext): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fallback-shipping-method-id');
        $shippingMethod->setName('fallback-shipping-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }

            static::assertNotNull($shippingMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->remove($error->getId());
            $errors->add(new ShippingMethodChangedError(
                oldShippingMethodId: $error->getShippingMethodId(),
                oldShippingMethodName: $error->getName(),
                newShippingMethodId: $shippingMethod->getId(),
                newShippingMethodName: $shippingMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $shippingMethod;
    }

    public function callbackShippingMethodSwitcherUnswitchableCart(ErrorCollection $errors, ChannelContext $channelContext): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fallback-shipping-method-id');
        $shippingMethod->setName('fallback-shipping-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }

            static::assertNotNull($shippingMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->add(new ShippingMethodChangedError(
                oldShippingMethodId: $error->getShippingMethodId(),
                oldShippingMethodName: $error->getName(),
                newShippingMethodId: $shippingMethod->getId(),
                newShippingMethodName: $shippingMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $shippingMethod;
    }

    public function callbackPaymentMethodSwitcherReturnOriginalMethod(ErrorCollection $errors, ChannelContext $channelContext): PaymentMethodEntity
    {
        return $channelContext->getPaymentMethod();
    }

    public function callbackPaymentMethodSwitcherReturnFallbackMethod(ErrorCollection $errors, ChannelContext $channelContext): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('fallback-payment-method-id');
        $paymentMethod->setName('fallback-payment-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }

            static::assertNotNull($paymentMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->remove($error->getId());
            $errors->add(new PaymentMethodChangedError(
                oldPaymentMethodId: $error->getPaymentMethodId(),
                oldPaymentMethodName: $error->getName(),
                newPaymentMethodId: $paymentMethod->getId(),
                newPaymentMethodName: $paymentMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $paymentMethod;
    }

    public function callbackPaymentMethodSwitcherUnswitchableCart(ErrorCollection $errors, ChannelContext $channelContext): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('fallback-payment-method-id');
        $paymentMethod->setName('fallback-payment-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }

            static::assertNotNull($paymentMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->add(new PaymentMethodChangedError(
                oldPaymentMethodId: $error->getPaymentMethodId(),
                oldPaymentMethodName: $error->getName(),
                newPaymentMethodId: $paymentMethod->getId(),
                newPaymentMethodName: $paymentMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $paymentMethod;
    }

    private function getCart(): Cart
    {
        $cart = new Cart('cart-token');
        $cart->add(
            (new LineItem('line-item-id-1', 'line-item-type-1'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-1')
                ->assign(['uniqueIdentifier' => 'line-item-id-1'])
        )->add(
            (new LineItem('line-item-id-2', 'line-item-type-2'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-2')
                ->assign(['uniqueIdentifier' => 'line-item-id-2'])
        );

        return $cart;
    }

    private function getCartErrorCollection(bool $blockShippingMethod = false, bool $blockPaymentMethod = false): ErrorCollection
    {
        $cartErrors = new ErrorCollection();
        if ($blockShippingMethod) {
            $cartErrors->add(
                new ShippingMethodBlockedError(
                    id: 'original-shipping-method-id',
                    name: 'original-shipping-method-name',
                    reason: 'reason',
                )
            );
        }

        if ($blockPaymentMethod) {
            $cartErrors->add(
                new PaymentMethodBlockedError(
                    id: 'original-payment-method-id',
                    name: 'original-payment-method-name',
                    reason: 'reason',
                )
            );
        }

        return $cartErrors;
    }

    /**
     * @param callable(ErrorCollection, ChannelContext): ShippingMethodEntity|null $shippingSwitcherCallbackMethod
     * @param callable(ErrorCollection, ChannelContext): PaymentMethodEntity|null $paymentSwitcherCallbackMethod
     */
    private function getFrontendCartFacade(
        Cart $cart,
        ?callable $shippingSwitcherCallbackMethod = null,
        ?callable $paymentSwitcherCallbackMethod = null,
        ?CartCalculator $cartCalculator = null,
        ?AbstractCheckoutGatewayRoute $checkoutGatewayRoute = null,
    ): FrontendCartFacade {
        $cartService = static::createStub(CartService::class);
        $cartService->method('getCart')->willReturn($cart);

        $shippingCallback = $shippingSwitcherCallbackMethod ?? $this->callbackShippingMethodSwitcherReturnOriginalMethod(...);
        $realShippingSwitcher = new BlockedShippingMethodSwitcher(static::createStub(ShippingMethodRoute::class));
        $blockedShippingMethodSwitcher = static::createStub(BlockedShippingMethodSwitcher::class);
        $blockedShippingMethodSwitcher->method('switch')->willReturnCallback(
            static fn (ErrorCollection $errors, ChannelContext $context, ?ShippingMethodCollection $methods = null): ShippingMethodEntity => $methods === null ? $shippingCallback($errors, $context) : $realShippingSwitcher->switch($errors, $context, $methods),
        );

        $paymentCallback = $paymentSwitcherCallbackMethod ?? $this->callbackPaymentMethodSwitcherReturnOriginalMethod(...);
        $realPaymentSwitcher = new BlockedPaymentMethodSwitcher(static::createStub(PaymentMethodRoute::class));
        $blockedPaymentMethodSwitcher = static::createStub(BlockedPaymentMethodSwitcher::class);
        $blockedPaymentMethodSwitcher->method('switch')->willReturnCallback(
            static fn (ErrorCollection $errors, ChannelContext $context, ?PaymentMethodCollection $methods = null): PaymentMethodEntity => $methods === null ? $paymentCallback($errors, $context) : $realPaymentSwitcher->switch($errors, $context, $methods),
        );

        $contextSwitchRoute = static::createStub(ContextSwitchRoute::class);

        if (!$cartCalculator) {
            $cartCalculator = static::createStub(CartCalculator::class);
            $cartCalculator->method('calculate')->willReturnArgument(0);
        }

        $cartPersister = static::createStub(CartPersister::class);

        return new FrontendCartFacade(
            $cartService,
            $blockedShippingMethodSwitcher,
            $blockedPaymentMethodSwitcher,
            $contextSwitchRoute,
            $cartCalculator,
            $cartPersister,
            $checkoutGatewayRoute ?? static::createStub(AbstractCheckoutGatewayRoute::class),
        );
    }

    private function getChannelContextWithDefaults(string $defaultPaymentMethodId, string $defaultShippingMethodId): ChannelContext
    {
        $shippingMethod = $this->createShippingMethod('original-shipping-method-id', 'original-shipping-method-name');
        $paymentMethod = $this->createPaymentMethod('original-payment-method-id', 'original-payment-method-name');

        $channelContext = Generator::generateChannelContext(paymentMethod: $paymentMethod, shippingMethod: $shippingMethod);
        $channelContext->getChannel()->setPaymentMethodId($defaultPaymentMethodId);
        $channelContext->getChannel()->setShippingMethodId($defaultShippingMethodId);

        return $channelContext;
    }

    private function createPaymentMethod(string $id, string $name): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($id);
        $paymentMethod->setName($name);
        $paymentMethod->setTranslated(['name' => $name]);

        return $paymentMethod;
    }

    private function createShippingMethod(string $id, string $name): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);
        $shippingMethod->setName($name);
        $shippingMethod->setTranslated(['name' => $name]);

        return $shippingMethod;
    }

    /**
     * @return Stub&ChannelContext
     */
    private function getChannelContext(): Stub
    {
        $channelContext = static::createStub(ChannelContext::class);
        $this->configureChannelContext($channelContext);

        return $channelContext;
    }

    /**
     * @return MockObject&ChannelContext
     */
    private function getChannelContextMock(): MockObject
    {
        $channelContext = $this->createMock(ChannelContext::class);
        $this->configureChannelContext($channelContext);

        return $channelContext;
    }

    private function configureChannelContext(MockObject|Stub $channelContext): void
    {
        $channel = new ChannelEntity();
        $channel->setId(TestDefaults::CHANNEL);
        $channel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        $channelContext->method('getChannel')->willReturn($channel);
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('original-shipping-method-id');
        $shippingMethod->setName('original-shipping-method-name');

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('original-payment-method-id');
        $paymentMethod->setName('original-payment-method-name');

        $channelContext->method('getShippingMethod')->willReturn($shippingMethod);
        $channelContext->method('getPaymentMethod')->willReturn($paymentMethod);
    }
}
