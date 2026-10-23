<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Confirm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Error\ErrorCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Validation\AddressValidationFactory;
use Contena\Core\System\User\Validation\Constraint\UserZipCode;
use Contena\Core\Checkout\Gateway\Channel\CheckoutGatewayRouteResponse;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartGatewayResult;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPage;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(CheckoutConfirmPageLoader::class)]
class CheckoutConfirmPageLoaderTest extends TestCase
{
    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new CheckoutConfirmPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            $this->getContextWithDummyUser()
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new CheckoutConfirmPage();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            $this->getContextWithDummyUser()
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testPaymentAndShippingMethodsAreSetToPage(): void
    {
        $paymentMethods = new PaymentMethodCollection([
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $response = new CheckoutGatewayRouteResponse(
            $paymentMethods,
            $shippingMethods,
            new ErrorCollection()
        );

        $cartService = static::createStub(FrontendCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn(new FrontendCartGatewayResult(new Cart('test'), $response));

        $page = $this->createLoader(cartService: $cartService)->load(
            new Request(),
            $this->getContextWithDummyUser()
        );

        static::assertSame($paymentMethods, $page->getPaymentMethods());
        static::assertSame($shippingMethods, $page->getShippingMethods());
    }

    public function testUserNotLoggedInException(): void
    {
        $context = static::createStub(ChannelContext::class);
        $context
            ->method('getUser')
            ->willReturn(null);

        $this->expectExceptionObject(CartException::userNotLoggedIn());

        $this->createLoader()->load(new Request(), $context);
    }

    public function testViolationsAreAddedAsCartErrorsWithSameAddress(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Test error',
                null,
                [],
                'root',
                null,
                'invalidValue'
            ),
        ]);

        $validator = static::createStub(DataValidator::class);
        $validator
            ->method('getViolations')
            ->willReturn($violations);

        $cart = new Cart('test');

        $cartService = static::createStub(FrontendCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult($cart));

        $page = $this->createLoader(cartService: $cartService, validator: $validator)->load(new Request(), $this->getContextWithDummyUser());

        static::assertCount(1, $page->getCart()->getErrors());
        static::assertArrayHasKey('billing-address-invalid', $page->getCart()->getErrors()->getElements());

        $error = $page->getCart()->getErrors()->first();

        static::assertNotNull($error);
        static::assertInstanceOf(AddressValidationError::class, $error);
        static::assertTrue($error->isBillingAddress());

        static::assertCount(1, $error->getViolations());

        $violation = $error->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());
    }

    public function testViolationsAreAddedAsCartErrorsWithDifferentAddresses(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Test error',
                null,
                [],
                'root',
                null,
                'invalidValue'
            ),
        ]);

        $validator = static::createStub(DataValidator::class);
        $validator
            ->method('getViolations')
            ->willReturn($violations);

        $cart = new Cart('test');

        $cartService = static::createStub(FrontendCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult($cart));

        $context = $this->getContextWithDummyUser();

        static::assertNotNull($context->getUser());

        // different shipping address
        $context->getUser()->assign([
            'activeShippingAddress' => (new UserAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]),
        ]);

        $page = $this->createLoader(cartService: $cartService, validator: $validator)->load(new Request(), $context);

        static::assertCount(2, $page->getCart()->getErrors());
        static::assertArrayHasKey('billing-address-invalid', $page->getCart()->getErrors()->getElements());
        static::assertArrayHasKey('shipping-address-invalid', $page->getCart()->getErrors()->getElements());

        $billingAddressError = $page->getCart()->getErrors()->first();

        static::assertNotNull($billingAddressError);
        static::assertInstanceOf(AddressValidationError::class, $billingAddressError);
        static::assertTrue($billingAddressError->isBillingAddress());

        static::assertCount(1, $billingAddressError->getViolations());

        $violation = $billingAddressError->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());

        $shippingAddressError = $page->getCart()->getErrors()->first();

        static::assertNotNull($shippingAddressError);
        static::assertInstanceOf(AddressValidationError::class, $shippingAddressError);
        static::assertTrue($shippingAddressError->isBillingAddress());

        static::assertCount(1, $shippingAddressError->getViolations());

        $violation = $shippingAddressError->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());
    }

    public function testValidatorNotCalledIfNoAddressGiven(): void
    {
        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->never())
            ->method('getViolations');

        $checkoutConfirmPageLoader = $this->createLoader(validator: $validator);

        $context = $this->getContextWithDummyUser();

        static::assertNotNull($context->getUser());

        $context->getUser()->assign([
            'activeBillingAddress' => null,
            'activeShippingAddress' => null,
        ]);

        $checkoutConfirmPageLoader->load(new Request(), $context);
    }

    public function testValidationEventIsDispatched(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();

        $addressValidationMock = $this->createMock(AddressValidationFactory::class);

        $checkoutConfirmPageLoader = $this->createLoader(
            eventDispatcher: $eventDispatcher,
            addressValidationFactory: $addressValidationMock,
        );

        $addressValidationMock->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(
            new DataValidationDefinition('address.create'),
            new DataValidationDefinition('address.update'),
        );

        $checkoutConfirmPageLoader->load(new Request(), $this->getContextWithDummyUser());

        $events = $eventDispatcher->getEvents();
        static::assertCount(3, $events);

        static::assertInstanceOf(BuildValidationEvent::class, $events['framework.validation.address.create']);
        static::assertInstanceOf(BuildValidationEvent::class, $events['framework.validation.address.update']);
        static::assertInstanceOf(CheckoutConfirmPageLoadedEvent::class, $events[0]);
    }

    public function testCartServiceIsCalledTaxedAndWithNoCaching(): void
    {
        $cartService = $this->createMock(FrontendCartFacade::class);
        $cartService
            ->expects($this->once())
            ->method('getWithCheckoutGateway')
            ->with(static::isInstanceOf(Request::class), 'token', static::isInstanceOf(ChannelContext::class), false, true)
            ->willReturn($this->createCartGatewayResult());

        $checkoutConfirmPageLoader = $this->createLoader(
            cartService: $cartService,
        );

        $checkoutConfirmPageLoader->load(new Request(), $this->getContextWithDummyUser());
    }

    public function testValidationEventIsDispatchedWithZipcodeDefinition(): void
    {
        $countryId = Uuid::randomHex();

        $cart = new Cart('test');

        $cartService = static::createStub(FrontendCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult($cart));

        $addressValidation = static::createStub(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn(new DataValidationDefinition('address.create'));

        $dispatcher = static::createStub(EventDispatcher::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function ($validationEvent) use ($countryId) {
            if (!$validationEvent instanceof BuildValidationEvent) {
                return $validationEvent;
            }

            $properties = $validationEvent->getDefinition()->getProperties();
            static::assertArrayHasKey('zipcode', $properties);
            $zipcode = $properties['zipcode'][0];
            static::assertNotNull($zipcode);
            static::assertInstanceOf(UserZipCode::class, $zipcode);

            $message = $zipcode->getMessage();

            static::assertSame($message, (new UserZipCode(countryId: $countryId))->getMessage());

            return $validationEvent;
        });

        $checkoutConfirmPageLoader = $this->createLoader(
            eventDispatcher: $dispatcher,
            cartService: $cartService,
            addressValidationFactory: $addressValidation,
        );

        $context = $this->getContextWithDummyUser();

        $checkoutConfirmPageLoader->load(new Request(), $context);
    }

    private function createLoader(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?FrontendCartFacade $cartService = null,
        ?GenericPageLoader $pageLoader = null,
        ?DataValidationFactoryInterface $addressValidationFactory = null,
        ?DataValidator $validator = null,
    ): CheckoutConfirmPageLoader {
        return new CheckoutConfirmPageLoader(
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
            $cartService ?? $this->createCartService(),
            $pageLoader ?? static::createStub(GenericPageLoader::class),
            $addressValidationFactory ?? static::createStub(DataValidationFactoryInterface::class),
            $validator ?? static::createStub(DataValidator::class),
            static::createStub(AbstractTranslator::class),
        );
    }

    private function createCartService(): FrontendCartFacade
    {
        $cartService = static::createStub(FrontendCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn($this->createCartGatewayResult());

        return $cartService;
    }

    private function createCartGatewayResult(?Cart $cart = null): FrontendCartGatewayResult
    {
        return new FrontendCartGatewayResult(
            $cart ?? new Cart('test'),
            new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection()
            )
        );
    }

    private function getContextWithDummyUser(): ChannelContext
    {
        $address = (new UserAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]);

        $user = new UserEntity();
        $user->assign([
            'activeBillingAddress' => $address,
            'activeShippingAddress' => $address,
        ]);

        $context = static::createStub(ChannelContext::class);
        $context
            ->method('getUser')
            ->willReturn($user);
        $context
            ->method('getToken')
            ->willReturn('token');

        return $context;
    }
}
