<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Channel\AbstractRegisterConfirmRoute;
use Contena\Core\System\Member\Channel\AbstractRegisterRoute;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Frontend\Controller\RegisterController;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPage;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\MemberGroupRegistration\AbstractMemberGroupRegistrationPageLoader;
use Contena\Frontend\Page\Account\MemberGroupRegistration\MemberGroupRegistrationPage;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    private AccountLoginPageLoader&Stub $accountLoginPageLoader;

    private AbstractMemberGroupRegistrationPageLoader&Stub $memberGroupRegistrationPageLoader;

    private AbstractRegisterRoute&Stub $registerRoute;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = static::createStub(AccountLoginPageLoader::class);
        $this->registerRoute = static::createStub(AbstractRegisterRoute::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->memberGroupRegistrationPageLoader = static::createStub(AbstractMemberGroupRegistrationPageLoader::class);
    }

    public function testAccountRegister(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.register.page');
        $dataBag = new RequestDataBag();
        $page = new AccountLoginPage();

        $accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $accountLoginPageLoader
            ->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $controller = $this->createController(accountLoginPageLoader: $accountLoginPageLoader);
        $controller->accountRegisterPage($request, $dataBag, $context);

        static::assertSame($page, $controller->renderFrontendParameters['page']);
        static::assertSame($dataBag, $controller->renderFrontendParameters['data']);
        static::assertSame('frontend.account.home.page', $controller->renderFrontendParameters['redirectTo'] ?? '');
        static::assertSame('[]', $controller->renderFrontendParameters['redirectParameters'] ?? '');
        static::assertSame('frontend.account.register.page', $controller->renderFrontendParameters['errorRoute'] ?? '');
    }

    public function testMemberGroupRegistration(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.member-group-registration.page');
        $dataBag = new RequestDataBag();
        $page = new MemberGroupRegistrationPage();
        $page->setGroup(new MemberGroupEntity());
        $memberGroupId = Uuid::randomHex();

        $memberGroupRegistrationPageLoader = $this->createMock(AbstractMemberGroupRegistrationPageLoader::class);
        $memberGroupRegistrationPageLoader
            ->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $controller = $this->createController(memberGroupRegistrationPageLoader: $memberGroupRegistrationPageLoader);
        $controller->memberGroupRegistration($memberGroupId, $request, $dataBag, $context);

        static::assertSame($page, $controller->renderFrontendParameters['page']);
        static::assertSame($dataBag, $controller->renderFrontendParameters['data']);
        static::assertSame('frontend.account.home.page', $controller->renderFrontendParameters['redirectTo'] ?? '');
        static::assertSame('frontend.account.member-group-registration.page', $controller->renderFrontendParameters['errorRoute'] ?? '');
        static::assertSame(json_encode(['memberGroupId' => $memberGroupId], \JSON_THROW_ON_ERROR), $controller->renderFrontendParameters['errorParameters'] ?? '');
    }

    public function testRegisterSuccess(): void
    {
        $context = Generator::generateChannelContext();
        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
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
        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag([
            'email' => 'foo@bar.de',
            'password' => 'password',
        ]);

        $this->systemConfigService->set('core.loginRegistration.requireEmailConfirmation', true, $context->getChannelId());
        $this->systemConfigService->set('core.loginRegistration.requirePasswordConfirmation', true, $context->getChannelId());

        $expectedDefinition = new DataValidationDefinition('frontend.confirmation');
        $expectedDefinition->add('emailConfirmation', new NotBlank(), new EqualTo(value: 'foo@bar.de'));
        $expectedDefinition->add('passwordConfirmation', new NotBlank(), new EqualTo(value: 'password'));

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
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
        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();

        $this->systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true, $context->getChannelId());

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->with($dataBag, $context, false, new DataValidationDefinition('frontend.confirmation'));

        $controller = $this->createController(registerRoute: $registerRoute);
        $response = $controller->register($request, $dataBag, $context);

        static::assertSame(['success' => ['account.optInRegistrationAlert']], $controller->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.register.page', $response->getTargetUrl());
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testRegisterWithNoErrorRouteParam(): void
    {
        $context = Generator::generateChannelContext();
        $request = $this->createRegisterRequest();
        $dataBag = new RequestDataBag();

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->createController(registerRoute: $registerRoute);

        $this->expectExceptionObject(RoutingException::missingRequestParameter('errorRoute'));
        $controller->register($request, $dataBag, $context);
    }

    public function testRegisterWithErrorRouteParamEmpty(): void
    {
        $context = Generator::generateChannelContext();
        $request = $this->createRegisterRequest();
        $request->request->set('errorRoute', '');

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->createController(registerRoute: $registerRoute);
        $response = $controller->register($request, new RequestDataBag(), $context);

        static::assertSame('frontend.account.register.page', $request->request->get('errorRoute'));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterWithViolation(): void
    {
        $context = Generator::generateChannelContext();
        $request = $this->createRegisterRequest();
        $request->request->set('errorRoute', 'some-url');

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->createController(registerRoute: $registerRoute);
        $response = $controller->register($request, new RequestDataBag(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function createController(
        ?AccountLoginPageLoader $accountLoginPageLoader = null,
        ?AbstractRegisterRoute $registerRoute = null,
        ?AbstractMemberGroupRegistrationPageLoader $memberGroupRegistrationPageLoader = null,
    ): RegisterControllerTestClass {
        $doubleSubmitGuard = new DoubleSubmitGuard(
            new LockFactory(new InMemoryStore()),
            new ArrayAdapter(),
            new NullLogger(),
        );

        return new RegisterControllerTestClass(
            $accountLoginPageLoader ?? $this->accountLoginPageLoader,
            $registerRoute ?? $this->registerRoute,
            static::createStub(AbstractRegisterConfirmRoute::class),
            $this->systemConfigService,
            $memberGroupRegistrationPageLoader ?? $this->memberGroupRegistrationPageLoader,
            static::createStub(EntityRepository::class),
            $doubleSubmitGuard,
        );
    }

    private function createRegisterRequest(): Request
    {
        $request = new Request();
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'https://example.com');

        return $request;
    }
}

/**
 * @internal
 */
class RegisterControllerTestClass extends RegisterController
{
    use FrontendControllerMockTrait;
}
