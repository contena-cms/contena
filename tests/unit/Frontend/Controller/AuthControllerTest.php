<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Channel\AbstractImitateMemberRoute;
use Contena\Core\System\Member\Channel\AbstractLoginRoute;
use Contena\Core\System\Member\Channel\AbstractLogoutRoute;
use Contena\Core\System\Member\Channel\AbstractResetPasswordRoute;
use Contena\Core\System\Member\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\System\Member\Exception\BadCredentialsException;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\AuthController;
use Contena\Frontend\Page\Account\Login\AccountLoginPage;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[CoversClass(AuthController::class)]
class AuthControllerTest extends TestCase
{
    private AuthControllerTestClass $controller;

    private AccountLoginPageLoader&Stub $accountLoginPageLoader;

    private AbstractSendPasswordRecoveryMailRoute&Stub $passwordRecoveryPageLoader;

    protected function setUp(): void
    {
        $this->accountLoginPageLoader = static::createStub(AccountLoginPageLoader::class);
        $this->passwordRecoveryPageLoader = static::createStub(AbstractSendPasswordRecoveryMailRoute::class);
        $this->controller = $this->createController();
    }

    public function testAccountRegister(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.login.page');
        $dataBag = new RequestDataBag();
        $page = new AccountLoginPage();

        $accountLoginPageLoader = $this->createMock(AccountLoginPageLoader::class);
        $accountLoginPageLoader
            ->expects($this->once())
            ->method('load')
            ->with($request, $context)
            ->willReturn($page);

        $controller = $this->createController($accountLoginPageLoader);
        $controller->loginPage($request, $dataBag, $context);

        static::assertSame($page, $controller->renderFrontendParameters['page']);
        static::assertSame($dataBag, $controller->renderFrontendParameters['data']);
        static::assertSame('frontend.account.home.page', $controller->renderFrontendParameters['redirectTo'] ?? '');
        static::assertSame('[]', $controller->renderFrontendParameters['redirectParameters'] ?? '');
    }

    #[DataProvider('loginRedirectProvider')]
    public function testLoginWithBadCredentialsForwardsToCorrectRoute(?string $redirectTo): void
    {
        $loginRoute = static::createStub(AbstractLoginRoute::class);
        $loginRoute->method('login')->willThrowException(new BadCredentialsException());

        $controller = $this->createController(loginRoute: $loginRoute);
        $request = new Request();
        if ($redirectTo !== null) {
            $request->request->set('redirectTo', $redirectTo);
        }

        $controller->login($request, new RequestDataBag(), Generator::generateChannelContext());

        static::assertSame('frontend.account.login.page', $controller->forwardToRoute);
        static::assertTrue($controller->forwardToRouteAttributes['loginError']);
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function loginRedirectProvider(): iterable
    {
        yield 'external URL attack' => ['https://www.contena.cn'];
        yield 'empty fallback' => [null];
    }

    public function testLogoutOptsTheResponseIntoClearSiteData(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext(member: new MemberEntity());

        $this->controller->logout($request, $context, new RequestDataBag());

        static::assertTrue($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA));
        static::assertArrayHasKey('frontend.account.login.page', $this->controller->redirected);
    }

    public function testLogoutWithoutMemberDoesNotOptIntoClearSiteData(): void
    {
        $request = new Request();

        $this->controller->logout($request, Generator::generateChannelContext(), new RequestDataBag());

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA));
    }

    public function testGenerateAccountRecoveryThrowsConstraintException(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.account.recover.page');

        $dataBag = new RequestDataBag();
        $data = new DataBag();
        $data->set('email', 'test@test');
        $dataBag->set('email', $data);

        $validation = new DataValidationDefinition('member.email.recover');
        $validation->add('email', new Email());

        $dataValidator = new DataValidator(Validation::createValidatorBuilder()->getValidator());
        $violations = $dataValidator->getViolations(['email' => 'test@test'], $validation);
        $exception = new ConstraintViolationException($violations, ['email' => 'test@test']);

        $passwordRecoveryPageLoader = $this->createMock(AbstractSendPasswordRecoveryMailRoute::class);
        $passwordRecoveryPageLoader
            ->expects($this->once())
            ->method('sendRecoveryMail')
            ->willThrowException($exception);

        $controller = $this->createController(passwordRecoveryPageLoader: $passwordRecoveryPageLoader);
        $controller->generateAccountRecovery($request, $dataBag, Generator::generateChannelContext());

        static::assertSame('frontend.account.recover.page', $controller->forwardToRoute);
        static::assertInstanceOf(ConstraintViolationException::class, $controller->forwardToRouteAttributes['formViolations']);
    }

    private function createController(
        ?AccountLoginPageLoader $accountLoginPageLoader = null,
        ?AbstractSendPasswordRecoveryMailRoute $passwordRecoveryPageLoader = null,
        ?AbstractLoginRoute $loginRoute = null,
    ): AuthControllerTestClass {
        $controller = new AuthControllerTestClass(
            $accountLoginPageLoader ?? $this->accountLoginPageLoader,
            $passwordRecoveryPageLoader ?? $this->passwordRecoveryPageLoader,
            static::createStub(AbstractResetPasswordRoute::class),
            $loginRoute ?? static::createStub(AbstractLoginRoute::class),
            static::createStub(AbstractLogoutRoute::class),
            static::createStub(AbstractImitateMemberRoute::class),
            static::createStub(AccountRecoverPasswordPageLoader::class),
        );

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $controller->setContainer($containerBuilder);

        return $controller;
    }
}

/**
 * @internal
 */
class AuthControllerTestClass extends AuthController implements ResetInterface
{
    use FrontendControllerMockTrait;
}
