<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Contena\Core\Framework\Log\Monolog\ExcludeFlowEventHandler;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Channel\AbstractLogoutRoute;
use Contena\Core\System\User\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\System\User\Channel\ConvertGuestRoute;
use Contena\Core\System\User\Channel\ImitateUserRoute;
use Contena\Core\System\User\Channel\LoginRoute;
use Contena\Core\System\User\Channel\ResetPasswordRoute;
use Contena\Core\System\User\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Controller\AuthController;
use Contena\Frontend\Controller\FrontendController;
use Contena\Frontend\Framework\Routing\ClearSiteDataListener;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountGuestLoginPageLoadedHook;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoadedHook;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPage;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Frontend\Test\Controller\AuthTestSubscriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
class AuthControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    private ChannelContext $channelContext;

    public function testSessionIsInvalidatedOnLogOut(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', true);

        $browser = $this->login();

        $session = $this->getSession();
        $contextToken = $session->get('sw-context-token');

        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $session = $this->getSession();

        $newContextToken = $session->get('sw-context-token');
        static::assertNotSame($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertNotSame($sessionId, $newSessionId);

        $oldCartExists = $connection->fetchOne('SELECT 1 FROM cart WHERE token = ?', [$contextToken]);
        static::assertFalse($oldCartExists);

        $oldContextExists = $connection->fetchOne('SELECT 1 FROM channel_api_context WHERE token = ?', [$contextToken]);
        static::assertFalse($oldContextExists);
    }

    public function testPerChannelTokensWhenUserBindingEnabled(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.systemWideLoginRegistration.isUserBoundToChannel', true);

        // Login on the default sales channel
        $browser = $this->login();
        $session = $this->getSession();

        $loginChannelId = $this->getFrontendChannelId();

        // Get the token for the login channel - should be stored in channel-specific key
        $loginChannelTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $loginChannelId;
        $loginChannelToken = $session->get($loginChannelTokenKey);

        static::assertNotNull($loginChannelToken, 'Login channel should have a channel-specific token');

        // Verify the default token key is synced with the login channel
        static::assertSame($loginChannelToken, $session->get('sw-context-token'), 'Default token should be synced with channel token');

        // Make another request on the same channel
        $browser->request('GET', '/');

        // Verify the channel-specific token is still preserved
        static::assertSame($loginChannelToken, $session->get($loginChannelTokenKey), 'Channel token should be preserved across requests');

        // Verify default token is still synced
        static::assertSame($loginChannelToken, $session->get('sw-context-token'), 'Default token should remain synced');
    }

    public function testGlobalTokenWhenUserBindingDisabled(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.systemWideLoginRegistration.isUserBoundToChannel', false);

        $browser = $this->login();
        $session = $this->getSession();

        $contextToken = $session->get('sw-context-token');
        $channelId = $this->getFrontendChannelId();

        // Make another request on the same channel
        $browser->request('GET', '/');

        // Token should remain the same (global token, not per-channel)
        static::assertSame($contextToken, $session->get('sw-context-token'), 'Global token should be preserved');

        // No channel-specific tokens should exist when binding is disabled
        $channelSpecificKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId;
        static::assertFalse($session->has($channelSpecificKey), 'Channel-specific tokens should not exist when binding is disabled');
    }

    public function testSessionIsInvalidatedOnLogoutAndInvalidateSettingFalse(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $browser = $this->login();

        $sessionCookie = $browser->getCookieJar()->get(PlatformRequest::FALLBACK_SESSION_NAME);
        static::assertNotNull($sessionCookie);

        $browser->request('GET', '/account/logout', []);
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $session = $this->getSession();

        if ($session->isStarted()) {
            // Close the old session
            $session->save();
        }

        // Set previous session id
        $session->setId($sessionCookie->getValue());
        // Set previous session cookie
        $browser->getCookieJar()->set($sessionCookie);

        // Try opening account page
        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/account', []);
        $response = $browser->getResponse();
        $session = $this->getSession();

        // Expect the session to have the same value as the initial session
        static::assertSame($session->getId(), $sessionCookie->getValue());

        // Expect a redirect response, since the old session should be destroyed
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testRedirectToAccountPageAfterLogin(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/login', []);
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account', $response->getTargetUrl());
    }

    public function testSessionIsMigratedOnLogOut(): void
    {
        $browser = $this->login();

        $session = $this->getSession();
        $contextToken = $session->get('sw-context-token');
        $sessionId = $session->getId();

        $browser->request('GET', '/account/logout');
        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/');
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $session = $this->getSession();

        $newContextToken = $session->get('sw-context-token');
        static::assertNotSame($contextToken, $newContextToken);

        $newSessionId = $session->getId();
        static::assertNotSame($sessionId, $newSessionId);
    }

    public function testLogoutSendsNoClearSiteDataHeaderByDefault(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/logout');
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        static::assertFalse($response->headers->has('Clear-Site-Data'));
    }

    /**
     * The container parameter cannot be changed at runtime, so the configured state is simulated by
     * hooking a second, configured listener instance into the real dispatcher.
     */
    public function testLogoutSendsClearSiteDataWhenDirectivesAreConfigured(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(FrontendRouteScope::ID . '.scope.response', (new ClearSiteDataListener(['cache', 'storage']))->onResponse(...));

        $browser = $this->login();
        $browser->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
        $browser->setServerParameter('HTTP_SEC_FETCH_MODE', 'navigate');
        $browser->setServerParameter('HTTP_SEC_FETCH_DEST', 'document');

        $browser->request('GET', '/account/logout');
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('"cache", "storage"', $response->headers->get('Clear-Site-Data'));
    }

    public function testOneUserUseOneContextAcrossSessions(): void
    {
        $browser = $this->login();

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $firstTimeLogin = $this->getSession();
        $firstTimeLoginSessionId = $firstTimeLogin->getId();
        $firstTimeLoginContextToken = $firstTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $browser->request('GET', '/account/logout', []);

        $response = $browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/', []);
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $browser->request(
            'POST',
            EnvironmentHelper::getVariable('APP_URL') . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ])
        );

        $secondTimeLogin = $this->getSession();
        $secondTimeLoginSessionId = $secondTimeLogin->getId();
        $secondTimeLoginContextToken = $secondTimeLogin->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        static::assertNotSame($firstTimeLoginSessionId, $secondTimeLoginSessionId);
        static::assertNotSame($firstTimeLoginContextToken, $secondTimeLoginContextToken);
    }

    public function testMergedHintIsAdded(): void
    {
        $user = $this->createUser();
        static::assertNotNull($user);

        $contextToken = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->createBlogOnDatabase($blogId, 'test.123', $context);
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::CHANNEL
        );

        static::getContainer()->get(ChannelContextPersister::class)->save(
            $contextToken,
            [
                'userId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            TestDefaults::CHANNEL,
            $user->getId()
        );

        $cart = new Cart($contextToken);

        $cart->add(new LineItem('blogId', LineItem::BLOG_LINE_ITEM_TYPE, $blogId));

        static::getContainer()->get(CartPersister::class)->save($cart, $channelContext);

        static::getContainer()->get('blog.repository')->delete([[
            'id' => $blogId,
        ]], $context);

        $request = new Request();
        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $request->setSession($session);
        static::getContainer()->get('request_stack')->push($request);

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('username', $user->getEmail());
        $requestDataBag->set('password', 'test12345');

        $channelContextNew = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL
        );

        static::getContainer()->get(AuthController::class)->login($request, $requestDataBag, $channelContextNew);
        $flashBag = $session->getFlashBag();

        static::assertNotCount(0, $infoFlash = $flashBag->get('danger'));
        static::assertSame(static::getContainer()->get('translator')->trans('checkout.blog-not-found', ['%s%' => 'Test blog']), $infoFlash[0]);
    }

    public function testAccountLoginPageLoadedHookScriptsAreExecuted(): void
    {
        $this->request('GET', '/account/login', []);

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountLoginPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountLoginAlreadyLoggedIn(): void
    {
        $controller = $this->getAuthController();

        $user = $this->createUser();
        static::assertNotNull($user);

        $request = $this->createRequest(
            'frontend.account.login.page',
            [
                'redirectTo' => 'frontend.account.order.single.page',
                'redirectParameters' => ['deepLinkCode' => 'example'],
                'loginError' => false,
                'waitTime' => 5,
            ],
            [
                ChannelContextService::CUSTOMER_ID => $user->getId(),
            ]
        );

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->login($request, new RequestDataBag($request->attributes->all()), $this->channelContext);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(302, $response->getStatusCode());

        static::assertSame('/account/order/example', $response->getTargetUrl());
    }

    public function testAccountLoginInactiveUser(): void
    {
        $controller = $this->getAuthController();

        $this->createUser(false, true);

        $request = $this->createRequest(
            'frontend.account.login.page',
            [
                'redirectTo' => 'frontend.account.order.single.page',
                'redirectParameters' => ['deepLinkCode' => 'example'],
                'loginError' => false,
                'waitTime' => 5,
            ]
        );

        $request->attributes->add(
            [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ]
        );

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->login($request, new RequestDataBag($request->attributes->all()), $this->channelContext);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testGenerateAccountRecovery(): void
    {
        $logger = static::getContainer()->get('monolog.logger.business_events');
        $handlers = $logger->getHandlers();
        $logger->setHandlers([
            new ExcludeFlowEventHandler(static::getContainer()->get(DoctrineSQLHandler::class), [
                UserAccountRecoverRequestEvent::EVENT_NAME,
            ]),
        ]);
        $testSubscriber = new AuthTestSubscriber();

        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($testSubscriber);

        $user = $this->createUser();
        static::assertNotNull($user);

        $controller = $this->getAuthController(static::getContainer()->get(SendPasswordRecoveryMailRoute::class));

        $request = $this->createRequest('frontend.account.recover.request');

        $data = new RequestDataBag([
            'email' => new RequestDataBag([
                'email' => $user->getEmail(),
            ]),
        ]);

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->generateAccountRecovery($request, $data, $this->channelContext);

        $flashBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        static::assertSame(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get(FrontendController::SUCCESS));
        static::assertSame('/account/recover', $response->headers->get('location') ?? '');

        // excluded events and its mail events should not be logged
        static::assertNotNull(AuthTestSubscriber::$userRecoveryEvent);
        $originalEvent = AuthTestSubscriber::$userRecoveryEvent->getName();

        $logCriteria = new Criteria();
        $logCriteria->addFilter(new OrFilter([
            new EqualsFilter('message', $originalEvent),
            new EqualsFilter('context.additionalData.eventName', $originalEvent),
        ]));

        $logEntries = static::getContainer()->get('log_entry.repository')->search(
            $logCriteria,
            Context::createDefaultContext()
        );

        static::assertCount(0, $logEntries->getEntities());
        $logger->setHandlers($handlers);
    }

    public function testAccountRecoveryPassword(): void
    {
        $controller = $this->getAuthController();

        $recoveryCreated = $this->createRecovery();

        $request = $this->createRequest(
            'frontend.account.recover.password.page',
            [
                'hash' => $recoveryCreated['hash'],
            ]
        );

        $request->attributes->add(
            [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ]
        );

        static::getContainer()->get('request_stack')->push($request);

        $testSubscriber = new AuthTestSubscriber();

        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($testSubscriber);

        $response = $controller->resetPasswordForm($request, $this->channelContext);

        static::assertSame(200, $response->getStatusCode());
        static::assertStringContainsString($recoveryCreated['hash'], (string) $response->getContent());

        static::assertNotNull(AuthTestSubscriber::$renderEvent);
        $parameters = AuthTestSubscriber::$renderEvent->getParameters();

        static::assertNotNull($parameters['page'] ?? null);
        $page = $parameters['page'];
        static::assertInstanceOf(AccountRecoverPasswordPage::class, $page);

        static::assertSame($recoveryCreated['hash'], $page->getHash());
        static::assertFalse($page->isHashExpired());
    }

    public function testAccountRecoveryPasswordExpired(): void
    {
        $controller = $this->getAuthController();

        $recoveryCreated = $this->createRecovery(true);

        $request = $this->createRequest(
            'frontend.account.recover.password.page',
            [
                'hash' => $recoveryCreated['hash'],
            ]
        );

        $request->attributes->add(
            [
                'username' => 'test@example.com',
                'password' => 'test12345',
            ]
        );

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->resetPasswordForm($request, $this->channelContext);

        $flashBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        static::assertSame(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertSame('/account/recover', $response->headers->get('location') ?? '');
    }

    public function testAccountRecoveryPasswordWrongHash(): void
    {
        $controller = $this->getAuthController();

        $request = $this->createRequest(
            'frontend.account.recover.password.page',
            [
                'hash' => 'wrong',
            ]
        );

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->resetPasswordForm($request, $this->channelContext);

        $flashBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        static::assertSame(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertSame('/account/recover', $response->headers->get('location') ?? '');
    }

    public function testAccountRecoveryPasswordNoHash(): void
    {
        $controller = $this->getAuthController();

        $request = $this->createRequest('frontend.account.recover.password.page');

        static::getContainer()->get('request_stack')->push($request);

        $response = $controller->resetPasswordForm($request, $this->channelContext);

        $flashBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        static::assertSame(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertSame('/account/recover', $response->headers->get('location') ?? '');
    }

    public function testAccountRecoveryPasswordNotMatchingNewPasswords(): void
    {
        $this->request('POST', '/account/recover/password', [
            'password' => [
                'newPassword' => 'kek12345',
                'newPasswordConfirm' => 'kek12345!',
            ],
        ]);

        $flashBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        static::assertContains(
            'The passwords you have entered do not match.',
            $flashBag->get('danger')
        );
    }

    public function testAccountGuestLoginPageLoadedHookScriptsAreExecuted(): void
    {
        $this->request('GET', '/account/guest/login', [
            'redirectTo' => 'frontend.account.order.single.page',
            'redirectParameters' => ['deepLinkCode' => 'foo'],
        ]);

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountGuestLoginPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountGuestLoginPageWithoutRedirectRedirects(): void
    {
        $response = $this->request('GET', '/account/guest/login', []);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('/account/login', $response->headers->get('location'));
    }

    public function testAccountGuestLoginPageWithMissingRedirectParametersRedirects(): void
    {
        // `redirectTo` points to a route that requires parameters (`/account/order/{deepLinkCode}`),
        // but `redirectParameters` is missing (e.g. the page was called directly). The form action URL
        // cannot be generated, so the admin_user should be redirected to the login page instead of getting a 500.
        $response = $this->request('GET', '/account/guest/login', ['redirectTo' => 'frontend.account.order.single.page']);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('/account/login', $response->headers->get('location'));
    }

    public function testLoginWithUnwantedQueryParameter(): void
    {
        $responseContent = $this->request(
            'GET',
            '/account/login?loginError=1&waitTime=<a%20href%3D"https%3A%2F%2Fde.wikipedia.org%2Fwiki%2FPhishing">Here<%2Fa>',
            []
        )->getContent();
        static::assertIsString($responseContent);
        static::assertStringNotContainsString('https://de.wikipedia.org/wiki/Phishing', $responseContent);
    }

    public function testConvert(): void
    {
        $browser = $this->register();
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('POST', '/account/convert', ['password' => 'password']);
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account', $response->getTargetUrl());
    }

    public function testRegisteredUserCanNotCovert(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/convert');
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account', $response->getTargetUrl());
    }

    public function testCanNotConvertWithoutPassword(): void
    {
        $browser = $this->register();
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('POST', '/account/convert');
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString('Passwords must have a minimum length of 8 characters.', $response->getContent());
    }

    public function testConvertHandelPasswordConfirm(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.requirePasswordConfirmation', true);

        $browser = $this->register();
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('POST', '/account/convert', [
            'password' => 'password',
            'passwordConfirmation' => 'pwd',
        ]);
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString('Confirmation field does not match.', $response->getContent());
    }

    public function testConvertWithAlreadyUsedEmailAddsErrorMessage(): void
    {
        $this->createUser();
        $browser = $this->register();
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $browser->request('POST', '/account/convert', [
            'password' => 'password',
            'passwordConfirmation' => 'pwd',
        ]);
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString('This email address has already been registered.', $response->getContent());
    }

    private function createBlogOnDatabase(string $blogId, string $blogNumber, Context $context): void
    {
        $taxId = Uuid::randomHex();

        $blog = [
            'id' => $blogId,
            'name' => 'Test blog',
            'blogNumber' => $blogNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['id' => $taxId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $blogId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $blogId,
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        static::getContainer()->get('blog.repository')->create([$blog], $context);
    }

    private function getFrontendChannelId(): string
    {
        $channelId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(channel_id)) FROM channel_domain WHERE url = :url',
            ['url' => EnvironmentHelper::getVariable('APP_URL')]
        );
        static::assertIsString($channelId);

        return $channelId;
    }

    private function login(): KernelBrowser
    {
        $user = $this->createUser();
        static::assertNotNull($user);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            EnvironmentHelper::getVariable('APP_URL') . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $user->getEmail(),
                'password' => 'test12345',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function register(): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            EnvironmentHelper::getVariable('APP_URL') . '/account/register',
            $this->tokenize('frontend.account.register.save', [
                'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
                'email' => 'test@example.com',
                'emailConfirmation' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'frontendUrl' => 'http://localhost',

                'billingAddress' => [
                    'countryId' => $this->getValidCountryId(),
                    'street' => 'Musterstrasse 13',
                    'zipcode' => '48599',
                    'city' => 'Epe',
                ],
            ])
        );

        return $browser;
    }

    private function createUser(bool $active = true, bool $doubleOptInReg = false, bool $guest = false): ?UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'doubleOptInRegistration' => $doubleOptInReg,
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'test@example.com',
            'password' => 'test12345',
            'firstName' => 'Max',
            'active' => $active,
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
            'guest' => $guest,
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], Context::createDefaultContext());

        return $repo->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();
    }

    private function getAuthController(?AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute = null): AuthController
    {
        $sendPasswordRecoveryMailRoute ??= static::createStub(AbstractSendPasswordRecoveryMailRoute::class);

        $controller = new AuthController(
            static::getContainer()->get(AccountLoginPageLoader::class),
            $sendPasswordRecoveryMailRoute,
            static::getContainer()->get(ResetPasswordRoute::class),
            static::getContainer()->get(LoginRoute::class),
            static::createStub(AbstractLogoutRoute::class),
            static::getContainer()->get(ImitateUserRoute::class),
            static::getContainer()->get(FrontendCartFacade::class),
            static::getContainer()->get(AccountRecoverPasswordPageLoader::class),
            static::getContainer()->get(ConvertGuestRoute::class),
            static::getContainer()->get(SystemConfigService::class),
        );
        $controller->setContainer(static::getContainer());

        return $controller;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $channelContextOptions
     */
    private function createRequest(string $route, array $params = [], array $channelContextOptions = []): Request
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class)->getDecorated();
        $this->channelContext = $channelContextFactory->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            $channelContextOptions
        );

        $request = Request::create((string) EnvironmentHelper::getVariable('APP_URL'));
        $request->query->add($params);
        $request->setSession($this->getSession());
        $request->attributes->add([
            '_route' => $route,
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => TestDefaults::CHANNEL,
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $this->channelContext,
            RequestTransformer::FRONTEND_URL => 'http://localhost',
        ]);

        return $request;
    }

    /**
     * @return array{user: UserEntity, hash: string, hashId: string}
     */
    private function createRecovery(bool $expired = false): array
    {
        $user = $this->createUser();
        static::assertNotNull($user);

        $hash = Random::getAlphanumericString(32);
        $hashId = Uuid::randomHex();

        static::getContainer()->get('user_recovery.repository')->create([
            [
                'id' => $hashId,
                'userId' => $user->getId(),
                'hash' => $hash,
            ],
        ], Context::createDefaultContext());

        if ($expired) {
            static::getContainer()->get(Connection::class)->update(
                'user_recovery',
                [
                    'created_at' => (new \DateTime())->sub(new \DateInterval('PT3H'))->format(
                        Defaults::STORAGE_DATE_TIME_FORMAT
                    ),
                ],
                [
                    'id' => Uuid::fromHexToBytes($hashId),
                ]
            );
        }

        return ['user' => $user, 'hash' => $hash, 'hashId' => $hashId];
    }
}
