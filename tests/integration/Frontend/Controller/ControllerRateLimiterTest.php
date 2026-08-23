<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\Channel\AbstractImitateMemberRoute;
use Contena\Core\System\Member\Channel\AbstractLogoutRoute;
use Contena\Core\System\Member\Channel\AbstractResetPasswordRoute;
use Contena\Core\System\Member\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\System\Member\Channel\ImitateMemberRoute;
use Contena\Core\System\Member\Channel\LoginRoute;
use Contena\Core\System\Member\Channel\LogoutRoute;
use Contena\Core\System\Member\Channel\ResetPasswordRoute;
use Contena\Core\System\Member\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\Member\MemberException;
use Contena\Frontend\Controller\AuthController;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class ControllerRateLimiterTest extends TestCase
{
    use ClockSensitiveTrait;
    use IntegrationTestBehaviour;

    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    private ChannelContext $channelContext;

    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            self::DEFAULT_WEB_CHANNEL_ID,
        );

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $session->getFlashBag()->clear();

        $this->translator = static::getContainer()->get('translator');
    }

    public function testGenerateAccountRecoveryRateLimit(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        static::mockTime($now);

        $passwordRecoveryMailRoute = $this->createMock(SendPasswordRecoveryMailRoute::class);
        $passwordRecoveryMailRoute->method('sendRecoveryMail')->willThrowException(new RateLimitExceededException($now->getTimestamp() + 10));

        $controller = new AuthController(
            static::getContainer()->get(AccountLoginPageLoader::class),
            $passwordRecoveryMailRoute,
            static::getContainer()->get(ResetPasswordRoute::class),
            static::getContainer()->get(LoginRoute::class),
            static::getContainer()->get(LogoutRoute::class),
            static::getContainer()->get(ImitateMemberRoute::class),
            static::getContainer()->get(AccountRecoverPasswordPageLoader::class),
        );
        $controller->setContainer(static::getContainer());

        $request = $this->createRequest('frontend.account.recover.request');

        static::getContainer()->get('request_stack')->push($request);

        $controller->generateAccountRecovery($request, new RequestDataBag([
            'email' => [
                'email' => 'test@example.com',
            ],
        ]), $this->channelContext);

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $flashBag = $session->getFlashBag();

        static::assertNotEmpty($flash = $flashBag->get('info'));
        static::assertSame($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 10]), $flash[0]);
    }

    public function testAuthControllerLoginShowsRateLimit(): void
    {
        $loginRoute = $this->createMock(LoginRoute::class);
        $loginRoute->method('login')->willThrowException(MemberException::memberAuthThrottled(5));

        $controller = new AuthController(
            static::getContainer()->get(AccountLoginPageLoader::class),
            $this->createMock(AbstractSendPasswordRecoveryMailRoute::class),
            $this->createMock(AbstractResetPasswordRoute::class),
            $loginRoute,
            $this->createMock(AbstractLogoutRoute::class),
            $this->createMock(AbstractImitateMemberRoute::class),
            static::getContainer()->get(AccountRecoverPasswordPageLoader::class),
        );
        $controller->setContainer(static::getContainer());

        $request = $this->createRequest('frontend.account.login');

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->login($request, new RequestDataBag([
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]), $this->channelContext);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $errorContent = $crawler->filterXPath('//form[@class="login-form"]//div[@class="alert-content-container"]')->text();

        static::assertStringContainsString($this->translator->trans('account.loginThrottled', ['%seconds%' => 5], 'messages', 'en-GB'), $errorContent);
    }

    private function createRequest(string $route): Request
    {
        $request = Request::create((string) EnvironmentHelper::getVariable('APP_URL'));
        $request->setSession($this->getSession());
        $request->attributes->add([
            '_route' => $route,
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => self::DEFAULT_WEB_CHANNEL_ID,
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $this->channelContext,
            RequestTransformer::FRONTEND_URL => 'http://localhost',
        ]);

        return $request;
    }
}
