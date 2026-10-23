<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Channel\RegisterConfirmRoute;
use Contena\Core\System\User\Channel\RegisterRoute;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Frontend\Controller\RegisterController;
use Contena\Frontend\Framework\AffiliateTracking\AffiliateTrackingListener;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPage;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\Register\AccountRegisterPageLoadedHook;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPage;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoadedHook;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoader;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPage;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoaderInterface;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(RegisterController::class)]
class RegisterControllerTest extends TestCase
{
    private RegisterControllerStub $controller;

    private AccountLoginPageLoader&Stub $accountLoginPageLoader;

    private CheckoutRegisterPageLoader&Stub $checkoutRegisterPageLoader;

    private CartService&Stub $cartService;

    private UserGroupRegistrationPageLoader&Stub $userGroupRegistrationPageLoader;

    private RegisterRoute&Stub $registerRoute;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = static::createStub(AccountLoginPageLoader::class);
        $this->registerRoute = static::createStub(RegisterRoute::class);
        $this->cartService = static::createStub(CartService::class);
        $this->checkoutRegisterPageLoader = static::createStub(CheckoutRegisterPageLoader::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->userGroupRegistrationPageLoader = static::createStub(UserGroupRegistrationPageLoader::class);

        $this->controller = $this->createController();
    }

    public function testAccountRegister(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.register.page');
        $dataBag = new RequestDataBag();
        $page = new AccountLoginPage();

        $accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $accountLoginPageLoader->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);
        $controller = $this->createController(accountLoginPageLoader: $accountLoginPageLoader);

        $controller->accountRegisterPage($request, $dataBag, $context);

        static::assertSame($page, $controller->recorder()->renderFrontendParameters['page']);
        static::assertSame($dataBag, $controller->recorder()->renderFrontendParameters['data']);
        static::assertSame('frontend.account.home.page', $controller->recorder()->renderFrontendParameters['redirectTo'] ?? '');
        static::assertSame('[]', $controller->recorder()->renderFrontendParameters['redirectParameters'] ?? '');
        static::assertSame('frontend.account.register.page', $controller->recorder()->renderFrontendParameters['errorRoute'] ?? '');
        static::assertInstanceOf(AccountRegisterPageLoadedHook::class, $controller->recorder()->calledHook);
    }

    public function testCheckoutRegister(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.checkout.register.page');
        $dataBag = new RequestDataBag();
        $page = new CheckoutRegisterPage();
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem('test', 'test'));

        $checkoutRegisterPageLoader = $this->createMock(CheckoutRegisterPageLoader::class);
        $checkoutRegisterPageLoader->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn($cart);

        $controller = $this->createController(cartService: $cartService, checkoutRegisterPageLoader: $checkoutRegisterPageLoader);

        $controller->checkoutRegisterPage($request, $dataBag, $context);

        static::assertSame($page, $controller->recorder()->renderFrontendParameters['page']);
        static::assertSame($dataBag, $controller->recorder()->renderFrontendParameters['data']);
        static::assertSame('frontend.checkout.confirm.page', $controller->recorder()->renderFrontendParameters['redirectTo'] ?? '');
        static::assertSame('frontend.checkout.register.page', $controller->recorder()->renderFrontendParameters['errorRoute'] ?? '');
        static::assertFalse($controller->recorder()->renderFrontendParameters['loginError'] ?? null);
        static::assertInstanceOf(CheckoutRegisterPageLoadedHook::class, $controller->recorder()->calledHook);
    }

    public function testCheckoutRegisterForwardsLoginError(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.checkout.register.page');
        $request->attributes->set('loginError', true);
        $request->attributes->set('waitTime', 5);
        $dataBag = new RequestDataBag();
        $page = new CheckoutRegisterPage();
        $cart = new Cart(Uuid::randomHex());
        $cart->add(new LineItem('test', 'test'));

        $checkoutRegisterPageLoader = static::createStub(CheckoutRegisterPageLoader::class);
        $checkoutRegisterPageLoader->method('load')->willReturn($page);

        $cartService = static::createStub(CartService::class);
        $cartService->method('getCart')->willReturn($cart);

        $controller = $this->createController(cartService: $cartService, checkoutRegisterPageLoader: $checkoutRegisterPageLoader);

        $controller->checkoutRegisterPage($request, $dataBag, $context);

        static::assertTrue($controller->recorder()->renderFrontendParameters['loginError'] ?? null);
        static::assertSame(5, $controller->recorder()->renderFrontendParameters['waitTime'] ?? null);
    }

    public function testUserGroupRegistration(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.user-group-registration.page');
        $dataBag = new RequestDataBag();
        $page = new UserGroupRegistrationPage();
        $page->setGroup(new UserGroupEntity());
        $userGroupId = Uuid::randomHex();

        $userGroupRegistrationPageLoader = $this->createMock(UserGroupRegistrationPageLoader::class);
        $userGroupRegistrationPageLoader->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);
        $controller = $this->createController(userGroupRegistrationPageLoader: $userGroupRegistrationPageLoader);

        $controller->userGroupRegistration($userGroupId, $request, $dataBag, $context);

        static::assertSame($page, $controller->recorder()->renderFrontendParameters['page']);
        static::assertSame($dataBag, $controller->recorder()->renderFrontendParameters['data']);
        static::assertSame('frontend.account.home.page', $controller->recorder()->renderFrontendParameters['redirectTo'] ?? '');
        static::assertSame('frontend.account.user-group-registration.page', $controller->recorder()->renderFrontendParameters['errorRoute'] ?? '');
        static::assertSame(json_encode(['userGroupId' => $userGroupId], \JSON_THROW_ON_ERROR), $controller->recorder()->renderFrontendParameters['errorParameters'] ?? '');
        static::assertInstanceOf(UserGroupRegistrationPageLoadedHook::class, $controller->recorder()->calledHook);
    }

    public function testRegisterSuccess(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();
        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->with($dataBag, $context, false, new DataValidationDefinition('frontend.confirmation'));
        $controller = $this->createController(registerRoute: $registerRoute);

        $response = $controller->register($request, $dataBag, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithValueConfirmation(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();
        $dataBag->set('email', 'foo@bar.de');
        $dataBag->set('password', 'password');
        $dataBag->set('createUserAccount', true);

        $this->systemConfigService->set('core.loginRegistration.requireEmailConfirmation', true, $context->getChannelId());
        $this->systemConfigService->set('core.loginRegistration.requirePasswordConfirmation', true, $context->getChannelId());

        $expectedDefinition = new DataValidationDefinition('frontend.confirmation');
        $expectedDefinition->add('emailConfirmation', new NotBlank(), new EqualTo(value: 'foo@bar.de'));
        $expectedDefinition->add('passwordConfirmation', new NotBlank(), new EqualTo(value: 'password'));
        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->with($dataBag, $context, false, $expectedDefinition);
        $controller = $this->createController(registerRoute: $registerRoute);

        $response = $controller->register($request, $dataBag, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithDoubleOptIn(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();
        $dataBag->set('createUserAccount', true);

        $this->systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true, $context->getChannelId());

        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->with($dataBag, $context, false, new DataValidationDefinition('frontend.confirmation'));
        $controller = $this->createController(registerRoute: $registerRoute);

        $response = $controller->register($request, $dataBag, $context);

        static::assertSame(['success' => ['account.optInRegistrationAlert']], $controller->recorder()->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.register.page', $response->getTargetUrl());
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testRegisterWithDoubleOptInGuest(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();
        $dataBag->set('createUserAccount', false);

        $this->systemConfigService->set('core.loginRegistration.doubleOptInGuestOrder', true, $context->getChannelId());

        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->with($dataBag, $context, false, new DataValidationDefinition('frontend.confirmation'));
        $controller = $this->createController(registerRoute: $registerRoute);

        $response = $controller->register($request, $dataBag, $context);

        static::assertSame(['success' => ['account.optInGuestAlert']], $controller->recorder()->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.register.page', $response->getTargetUrl());
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testRegisterWithNoErrorRouteParam(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();

        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerRoute->expects($this->once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));
        $controller = $this->createController(registerRoute: $registerRoute);

        $this->expectExceptionObject(RoutingException::missingRequestParameter('errorRoute'));
        $controller->register($request, $dataBag, $context);
    }

    public function testRegisterWithErrorRouteParamEmpty(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = $this->createRegisterRequest();
        $request->request->set('errorRoute', '');

        $dataBag = new RequestDataBag();

        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerRoute->expects($this->once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));
        $controller = $this->createController(registerRoute: $registerRoute);

        $response = $controller->register($request, $dataBag, $context);

        static::assertSame('frontend.account.register.page', $request->request->get('errorRoute'));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithViolation(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = $this->createRegisterRequest();
        $request->request->set('errorRoute', 'some-url');

        $dataBag = new RequestDataBag();

        $registerRoute = $this->createMock(RegisterRoute::class);
        $registerRoute->expects($this->once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));
        $controller = $this->createController(registerRoute: $registerRoute);

        $response = $controller->register($request, $dataBag, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithAffiliateTracking(): void
    {
        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $request = new Request();
        $request->attributes->set(RequestTransformer::FRONTEND_URL, $_SERVER['APP_URL']);
        $session = new Session(new MockArraySessionStorage());
        $session->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, 'affiliate-code');
        $session->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, 'affiliate-campaign');
        $request->setSession($session);

        $dataBag = new RequestDataBag();

        $this->controller->register($request, $dataBag, $context);

        static::assertSame('affiliate-code', $dataBag->get('affiliateCode'));
        static::assertSame('affiliate-campaign', $dataBag->get('campaignCode'));
    }

    private function createController(
        ?AccountLoginPageLoader $accountLoginPageLoader = null,
        ?RegisterRoute $registerRoute = null,
        ?CartService $cartService = null,
        ?CheckoutRegisterPageLoader $checkoutRegisterPageLoader = null,
        ?UserGroupRegistrationPageLoader $userGroupRegistrationPageLoader = null,
    ): RegisterControllerStub {
        $registerConfirmRoute = static::createStub(RegisterConfirmRoute::class);
        $userRepository = static::createStub(EntityRepository::class);
        $domainRepository = static::createStub(EntityRepository::class);

        $doubleSubmitGuard = new DoubleSubmitGuard(
            new LockFactory(new InMemoryStore()),
            new ArrayAdapter(),
            new NullLogger(),
        );

        return new RegisterControllerStub(
            $accountLoginPageLoader ?? $this->accountLoginPageLoader,
            $registerRoute ?? $this->registerRoute,
            $registerConfirmRoute,
            $cartService ?? $this->cartService,
            $checkoutRegisterPageLoader ?? $this->checkoutRegisterPageLoader,
            $this->systemConfigService,
            $userRepository,
            $userGroupRegistrationPageLoader ?? $this->userGroupRegistrationPageLoader,
            $domainRepository,
            static::createStub(HeaderPageletLoaderInterface::class),
            static::createStub(FooterPageletLoaderInterface::class),
            $doubleSubmitGuard,
        );
    }

    private function createRegisterRequest(): Request
    {
        $request = new Request();
        $request->attributes->set(RequestTransformer::FRONTEND_URL, $_SERVER['APP_URL']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
