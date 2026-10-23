<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Channel\AbstractImitateUserRoute;
use Contena\Core\System\User\Channel\AbstractLogoutRoute;
use Contena\Core\System\User\Channel\AbstractResetPasswordRoute;
use Contena\Core\System\User\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\ConvertGuestRoute;
use Contena\Core\System\User\Channel\ImitateUserRoute;
use Contena\Core\System\User\Channel\LoginRoute;
use Contena\Core\System\User\Channel\LogoutRoute;
use Contena\Core\System\User\Channel\ResetPasswordRoute;
use Contena\Core\System\User\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\User\Service\GuestAuthenticator;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\Channel\OrderRoute;
use Contena\Core\Content\ContactForm\Channel\AbstractContactFormRoute;
use Contena\Core\Content\ContactForm\Channel\ContactFormRoute;
use Contena\Core\Content\Newsletter\Channel\AbstractNewsletterSubscribeRoute;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Content\Newsletter\Channel\NewsletterUnsubscribeRoute;
use Contena\Core\Content\RevocationRequest\Channel\AbstractRevocationRequestRoute;
use Contena\Core\Content\RevocationRequest\Channel\RevocationRequestRoute;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Contena\Core\Framework\Test\RateLimiter\RateLimiterTestTrait;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Integration\Traits\OrderFixture;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Checkout\Order\OrderBlogAvailabilityResolver;
use Contena\Frontend\Controller\AuthController;
use Contena\Frontend\Controller\FormController;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\Order\AccountOrderPageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class ControllerRateLimiterTest extends TestCase
{
    use ClockSensitiveTrait;
    use UserTestTrait;
    use OrderFixture;
    use RateLimiterTestTrait;
    use FrontendControllerTestBehaviour;

    private Context $context;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    private ChannelContext $channelContext;

    private TranslatorInterface $translator;

    private static bool $rateLimitedKernelBooted = false;

    public static function setUpBeforeClass(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
        // shut down only: the next class boots its kernel lazily inside a test context, which
        // recompiles with the rate limiter restored
        KernelLifecycleManager::ensureKernelShutdown();
        self::$rateLimitedKernelBooted = false;
    }

    protected function setUp(): void
    {
        // the rate-limiter pass applies at container compile time, so this class needs a freshly
        // compiled kernel. It is booted here rather than in setUpBeforeClass(): a deprecation
        // triggered during a static-context kernel boot has no TestCase object on the call stack
        // and crashes PHPUnit's event system instead of being recorded
        if (!self::$rateLimitedKernelBooted) {
            KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
            self::$rateLimitedKernelBooted = true;
        }

        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);

        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class)->getDecorated();
        $this->channelContext = $channelContextFactory->create(Uuid::randomHex(), $this->ids->get('sales-channel'));

        $this->clearCache();

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $session->getFlashBag()->clear();

        $this->translator = static::getContainer()->get('translator');
    }

    public function testGenerateAccountRecoveryRateLimit(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        static::mockTime($now);

        $passwordRecoveryMailRoute = static::createStub(SendPasswordRecoveryMailRoute::class);
        $passwordRecoveryMailRoute->method('sendRecoveryMail')->willThrowException(new RateLimitExceededException($now->getTimestamp() + 10));

        $controller = new AuthController(
            static::getContainer()->get(AccountLoginPageLoader::class),
            $passwordRecoveryMailRoute,
            static::getContainer()->get(ResetPasswordRoute::class),
            static::getContainer()->get(LoginRoute::class),
            static::getContainer()->get(LogoutRoute::class),
            static::getContainer()->get(ImitateUserRoute::class),
            static::getContainer()->get(FrontendCartFacade::class),
            static::getContainer()->get(AccountRecoverPasswordPageLoader::class),
            static::getContainer()->get(ConvertGuestRoute::class),
            static::getContainer()->get(SystemConfigService::class),
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

        static::assertNotCount(0, $flash = $flashBag->get('info'));
        static::assertSame($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 10]), $flash[0]);
    }

    public function testAuthControllerGuestLoginShowsRateLimit(): void
    {
        $controller = new AuthController(
            static::getContainer()->get(AccountLoginPageLoader::class),
            static::createStub(AbstractSendPasswordRecoveryMailRoute::class),
            static::createStub(AbstractResetPasswordRoute::class),
            static::createStub(LoginRoute::class),
            static::createStub(AbstractLogoutRoute::class),
            static::createStub(AbstractImitateUserRoute::class),
            static::getContainer()->get(FrontendCartFacade::class),
            static::getContainer()->get(AccountRecoverPasswordPageLoader::class),
            static::getContainer()->get(ConvertGuestRoute::class),
            static::getContainer()->get(SystemConfigService::class)
        );
        $controller->setContainer(static::getContainer());

        $request = $this->createRequest('frontend.account.guest.login.page', [
            'redirectTo' => 'frontend.account.order.single.page',
            'redirectParameters' => ['deepLinkCode' => 'example'],
            'loginError' => false,
            'waitTime' => 5,
        ]);

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->guestLoginPage($request, $this->channelContext);

        $contentReturn = $response->getContent();
        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $contentReturn);

        $errorContent = $crawler->filterXPath('//div[@class="flashbags container"]//div[@class="alert-content-container"]')->text();

        static::assertStringContainsString($this->translator->trans('account.loginThrottled', ['%seconds%' => 5], 'messages', 'en-GB'), $errorContent);
    }

    public function testAuthControllerLoginShowsRateLimit(): void
    {
        $loginRoute = static::createStub(LoginRoute::class);
        $loginRoute->method('login')->willThrowException(UserException::userAuthThrottledException(5));

        $controller = new AuthController(
            static::getContainer()->get(AccountLoginPageLoader::class),
            static::createStub(AbstractSendPasswordRecoveryMailRoute::class),
            static::createStub(AbstractResetPasswordRoute::class),
            $loginRoute,
            static::createStub(AbstractLogoutRoute::class),
            static::createStub(AbstractImitateUserRoute::class),
            static::getContainer()->get(FrontendCartFacade::class),
            static::getContainer()->get(AccountRecoverPasswordPageLoader::class),
            static::getContainer()->get(ConvertGuestRoute::class),
            static::getContainer()->get(SystemConfigService::class)
        );
        $controller->setContainer(static::getContainer());

        $request = $this->createRequest('frontend.account.login');

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->login($request, new RequestDataBag([
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]), $this->channelContext);

        $contentReturn = $response->getContent();
        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $contentReturn);

        $errorContent = $crawler->filterXPath('//form[@class="login-form"]//div[@class="alert-content-container"]')->text();

        static::assertStringContainsString($this->translator->trans('account.loginThrottled', ['%seconds%' => 5], 'messages', 'en-GB'), $errorContent);
    }

    public function testFormControllerRateLimit(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        static::mockTime($now);

        $contactFormRoute = static::createStub(AbstractContactFormRoute::class);
        $contactFormRoute->method('load')->willThrowException(new RateLimitExceededException($now->getTimestamp() + 5));

        $controller = new FormController(
            $contactFormRoute,
            static::getContainer()->get(NewsletterSubscribeRoute::class),
            static::getContainer()->get(NewsletterUnsubscribeRoute::class),
            static::getContainer()->get(RevocationRequestRoute::class),
            static::getContainer()->get(ConstraintViolationTranslator::class),
        );
        $controller->setContainer(static::getContainer());

        $response = $controller->sendContactForm(new RequestDataBag([
        ]), $this->channelContext);

        $content = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $content);
        static::assertArrayHasKey('type', $content[0]);
        static::assertSame('info', $content[0]['type']);

        $contentReturn = $content[0]['alert'];
        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = $crawler->filterXPath('//div[@class="alert-content-container"]')->text();

        static::assertStringContainsString($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 5]), $errorContent);
    }

    public function testNewsletterSubscribeFormControllerRateLimit(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        static::mockTime($now);

        $newsletterRequestRoute = static::createStub(AbstractNewsletterSubscribeRoute::class);
        $newsletterRequestRoute->method('subscribeWithResponse')->willThrowException(new RateLimitExceededException($now->getTimestamp() + 5));

        $controller = new FormController(
            static::getContainer()->get(ContactFormRoute::class),
            $newsletterRequestRoute,
            static::getContainer()->get(NewsletterUnsubscribeRoute::class),
            static::getContainer()->get(RevocationRequestRoute::class),
            static::getContainer()->get(ConstraintViolationTranslator::class),
        );
        $controller->setContainer(static::getContainer());

        $response = $controller->handleNewsletter(new Request(), new RequestDataBag(['option' => FormController::SUBSCRIBE]), $this->channelContext);

        $content = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $content);
        static::assertArrayHasKey('type', $content[0]);
        static::assertSame('info', $content[0]['type']);

        $contentReturn = $content[0]['alert'];
        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = $crawler->filterXPath('//div[@class="alert-content-container"]')->text();

        static::assertStringContainsString($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 5]), $errorContent);
    }

    public function testNewsletterUnsubscribeFormControllerRateLimit(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        static::mockTime($now);

        $newsletterRequestRoute = static::createStub(NewsletterUnsubscribeRoute::class);
        $newsletterRequestRoute->method('unsubscribeWithResponse')->willThrowException(new RateLimitExceededException($now->getTimestamp() + 5));

        $controller = new FormController(
            static::getContainer()->get(ContactFormRoute::class),
            static::getContainer()->get(NewsletterSubscribeRoute::class),
            $newsletterRequestRoute,
            static::getContainer()->get(RevocationRequestRoute::class),
            static::getContainer()->get(ConstraintViolationTranslator::class),
        );
        $controller->setContainer(static::getContainer());

        $response = $controller->handleNewsletter(new Request(), new RequestDataBag([]), $this->channelContext);

        $content = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $content);
        static::assertArrayHasKey('type', $content[0]);
        static::assertSame('info', $content[0]['type']);

        $contentReturn = $content[0]['alert'];
        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = $crawler->filterXPath('//div[@class="alert-content-container"]')->text();

        static::assertStringContainsString($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 5]), $errorContent);
    }

    public function testRevocationRequestFormControllerRateLimit(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        static::mockTime($now);

        $abstractRevocationRequestRoute = static::createStub(AbstractRevocationRequestRoute::class);
        $abstractRevocationRequestRoute->method('request')->willThrowException(new RateLimitExceededException($now->getTimestamp() + 5));

        $controller = new FormController(
            static::getContainer()->get(ContactFormRoute::class),
            static::getContainer()->get(NewsletterSubscribeRoute::class),
            static::getContainer()->get(NewsletterUnsubscribeRoute::class),
            $abstractRevocationRequestRoute,
            static::getContainer()->get(ConstraintViolationTranslator::class),
        );
        $controller->setContainer(static::getContainer());

        $response = $controller->sendRevocationRequest(new RequestDataBag([
        ]), $this->channelContext);

        $content = \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $content);
        static::assertArrayHasKey('type', $content[0]);
        static::assertSame('info', $content[0]['type']);

        $contentReturn = $content[0]['alert'];
        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = $crawler->filterXPath('//div[@class="alert-content-container"]')->text();

        static::assertStringContainsString($this->translator->trans('error.rateLimitExceeded', ['%seconds%' => 5]), $errorContent);
    }

    public function testResetAccountOrderRateLimit(): void
    {
        $orderRoute = new OrderRoute(
            static::getContainer()->get('order.repository'),
            static::getContainer()->get('promotion.repository'),
            $this->mockResetLimiter([
                RateLimiter::GUEST_LOGIN => 1,
            ]),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(AccountService::class),
            new GuestAuthenticator(),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $order = $this->createUserWithOrder();

        $controller = new AccountOrderPageLoader(
            static::createStub(GenericPageLoader::class),
            static::createStub(EventDispatcher::class),
            $orderRoute,
            static::createStub(AbstractTranslator::class),
            static::createStub(OrderBlogAvailabilityResolver::class)
        );

        $controller->load(new Request([
            'email' => 'orderTest@example.com',
            'zipcode' => '12345',
        ], [], [
            'deepLinkCode' => $order->getDeepLinkCode(),
        ]), $this->channelContext);
    }

    public function testAccountOrderRateLimit(): void
    {
        $order = $this->createUserWithOrder();

        for ($i = 0; $i <= 10; ++$i) {
            $this->browser->request(
                'POST',
                '/account/order/' . $order->getDeepLinkCode(),
                $this->tokenize('frontend.account.order.single.page', [
                    'email' => 'orderTest@example.com',
                    'zipcode' => 'wrong',
                ])
            );

            $response = $this->browser->getResponse();
            static::assertInstanceOf(RedirectResponse::class, $response);

            $waitTime = $i >= 10 ? $this->queryFromString($response->getTargetUrl(), 'waitTime') : 0;

            $this->browser->request(
                'GET',
                $response->getTargetUrl()
            );

            $contentReturn = $this->browser->getResponse()->getContent();
            $crawler = new Crawler();
            $crawler->addHtmlContent((string) $contentReturn);

            $errorContent = $crawler->filterXPath('//div[@class="flashbags container"]//div[@class="alert-content-container"]')->text();

            if ($i >= 10) {
                static::assertStringContainsString($this->translator->trans('account.loginThrottled', ['%seconds%' => $waitTime]), $errorContent);
            } else {
                static::assertStringContainsString($this->translator->trans('account.orderGuestLoginWrongCredentials'), $errorContent);
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createRequest(string $route, array $params = []): Request
    {
        $request = new Request();
        $request->query->add($params);
        $request->setSession($this->getSession());
        $request->headers->set('HOST', 'localhost');
        $request->attributes->add([
            '_route' => $route,
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => $this->ids->get('sales-channel'),
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $this->channelContext,
            RequestTransformer::FRONTEND_URL => 'http://localhost',
        ]);

        return $request;
    }

    private function createUserWithOrder(): OrderEntity
    {
        $orderId = Uuid::randomHex();
        $userId = $this->createUser('orderTest@example.com', true);

        static::getContainer()->get('user.repository')->update([
            [
                'id' => $userId,
                'channelId' => $this->ids->get('sales-channel'),
            ],
        ], $this->context);

        $orderData = $this->getOrderData($orderId, $this->context);
        $orderData[0]['orderUser']['user'] = ['id' => $userId];
        $orderData[0]['orderUser']['email'] = 'orderTest@example.com';
        $orderData[0]['orderUser']['addresses'][0]['zipcode'] = '12345';
        $orderData[0]['addresses'][0]['zipcode'] = '12345';
        $orderData[0]['channelId'] = $this->ids->get('sales-channel');

        $orderRepository = static::getContainer()->get('order.repository');
        $orderRepository->create($orderData, $this->context);

        $order = $orderRepository->search(new Criteria([$orderId]), $this->context)->getEntities()->first();

        static::assertNotNull($order);
        static::assertInstanceOf(OrderEntity::class, $order);

        return $order;
    }

    private function queryFromString(string $url, string $param): string
    {
        $rawParams = \parse_url($url, \PHP_URL_QUERY);
        static::assertIsString($rawParams);

        \parse_str($rawParams, $params);

        static::assertArrayHasKey($param, $params);
        static::assertIsString($params[$param]);

        return $params[$param];
    }
}
