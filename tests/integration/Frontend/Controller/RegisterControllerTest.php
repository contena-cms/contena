<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\QueryDataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Channel\RegisterConfirmRoute;
use Contena\Core\System\User\Channel\RegisterRoute;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\RegisterController;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\Register\AccountRegisterPageLoadedHook;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoadedHook;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoader;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoader;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class RegisterControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use FrontendControllerTestBehaviour;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $token = Uuid::randomHex();
        $this->channelContext = $channelContextFactory->create($token, TestDefaults::CHANNEL);

        $session = $this->getSession();
        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        $session->getFlashBag()->clear();
    }

    public function testGuestRegisterWithRequirePasswordConfirmation(): void
    {
        $container = static::getContainer();

        $userRepository = $container->get('user.repository');

        $config = static::getContainer()->get(SystemConfigService::class);

        $systemConfigServiceMock = static::createStub(SystemConfigService::class);

        $systemConfigServiceMock
            ->method('get')
            ->willReturnCallback(static function (string $key) use ($config) {
                if ($key === 'core.loginRegistration.requirePasswordConfirmation') {
                    return true;
                }

                return $config->get($key);
            });

        $registerController = $this->getRegisterController($container, $systemConfigServiceMock, $userRepository);

        $data = $this->getRegistrationData();

        $request = $this->createRequest();

        $response = $registerController->register($request, $data, $this->channelContext);

        $users = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM user WHERE email = :mail', ['mail' => $data->get('email')]);

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $users);
        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));
    }

    public function testGuestRegister(): void
    {
        $data = $this->getRegistrationData();

        $request = $this->createRequest();

        $response = static::getContainer()->get(RegisterController::class)->register($request, $data, $this->channelContext);

        $users = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM user WHERE email = :mail', ['mail' => $data->get('email')]);

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $users);
        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));
    }

    public function testDoubleSubmittedGuestRegistrationCreatesOnlyOneUser(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);

        $data = $this->getRegistrationData();
        $email = (string) $data->get('email');
        $consumedToken = $this->channelContext->getToken();

        $firstRequest = $this->createMainRequest();
        $registerController->register($firstRequest, $data, $this->channelContext);

        static::assertNotSame($consumedToken, $this->channelContext->getToken());
        static::assertTrue($firstRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));

        $duplicateContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create($consumedToken, TestDefaults::CHANNEL);

        $otherEmail = 'erika.musterfrau@example.com';
        $otherData = $this->getRegistrationData(email: $otherEmail);
        $otherBillingAddress = $otherData->get('billingAddress');
        static::assertInstanceOf(DataBag::class, $otherBillingAddress);
        $otherBillingAddress->set('street', 'Andere Strasse 42');

        $duplicateRequest = $this->createMainRequest();
        $duplicateResponse = $registerController->register($duplicateRequest, $otherData, $duplicateContext);

        static::assertSame(200, $duplicateResponse->getStatusCode());
        static::assertCount(1, $this->fetchUserIdsByEmail($email));
        static::assertCount(0, $this->fetchUserIdsByEmail($otherEmail));
        static::assertFalse(
            $duplicateRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT),
            'the duplicate submission must be suppressed instead of registering again'
        );
    }

    public function testEveryStaleResubmissionOfASpentTokenIsSuppressed(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);
        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $data = $this->getRegistrationData();
        $email = (string) $data->get('email');
        $consumedToken = $this->channelContext->getToken();

        $registerController->register($this->createMainRequest(), $data, $this->channelContext);

        static::assertCount(1, $this->fetchUserIdsByEmail($email));

        for ($resubmission = 0; $resubmission < 2; ++$resubmission) {
            $staleContext = $contextFactory->create($consumedToken, TestDefaults::CHANNEL);
            $staleRequest = $this->createMainRequest();

            $registerController->register($staleRequest, $this->getRegistrationData(), $staleContext);

            static::assertFalse(
                $staleRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT),
                'resubmission ' . ($resubmission + 1) . ' must be suppressed'
            );
        }

        static::assertCount(1, $this->fetchUserIdsByEmail($email));
    }

    public function testDoubleSubmittedGuestRegistrationWithDoubleOptInCreatesOnlyOneUser(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.doubleOptInGuestOrder', true, TestDefaults::CHANNEL);

        $registerController = static::getContainer()->get(RegisterController::class);

        $data = $this->getRegistrationData();
        $email = (string) $data->get('email');
        $consumedToken = $this->channelContext->getToken();

        $registerController->register($this->createMainRequest(), $data, $this->channelContext);

        static::assertSame($consumedToken, $this->channelContext->getToken());
        static::assertCount(1, $this->fetchUserIdsByEmail($email));

        $registerController->register($this->createMainRequest(), $this->getRegistrationData(), $this->channelContext);

        static::assertCount(1, $this->fetchUserIdsByEmail($email));
    }

    public function testSuppressedDoubleSubmitKeepsTheCartOnTheOneRegisteredContext(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $cartService = static::getContainer()->get(CartService::class);
        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $registerController = static::getContainer()->get(RegisterController::class);

        $data = $this->getRegistrationData();
        $email = (string) $data->get('email');
        $consumedToken = $this->channelContext->getToken();

        $cart = $cartService->getCart($consumedToken, $this->channelContext);
        $cartService->add($cart, $this->createBlogLineItem(), $this->channelContext);

        $registerController->register($this->createMainRequest(), $data, $this->channelContext);

        $winnerToken = $this->channelContext->getToken();
        static::assertNotSame($consumedToken, $winnerToken);

        $duplicateContext = $contextFactory->create($consumedToken, TestDefaults::CHANNEL);
        $contextRowsBeforeDuplicate = (int) $connection->fetchOne('SELECT COUNT(*) FROM `channel_api_context`');

        $registerController->register($this->createMainRequest(), $this->getRegistrationData(), $duplicateContext);

        static::assertSame(
            $contextRowsBeforeDuplicate,
            (int) $connection->fetchOne('SELECT COUNT(*) FROM `channel_api_context`'),
            'the suppressed submission must not insert another sales channel context'
        );

        $userIds = $this->fetchUserIdsByEmail($email);
        static::assertCount(1, $userIds);
        static::assertSame(
            [$winnerToken],
            $connection->fetchFirstColumn(
                'SELECT `token` FROM `channel_api_context` WHERE `user_id` = :userId',
                ['userId' => Uuid::fromHexToBytes($userIds[0])]
            )
        );

        $winnerCart = $cartService->getCart(
            $winnerToken,
            $contextFactory->create($winnerToken, TestDefaults::CHANNEL),
            caching: false
        );

        static::assertCount(1, $winnerCart->getLineItems());
    }

    public function testSuppressedDoubleSubmitLeavesTheDuplicateSessionAnonymous(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);

        $consumedToken = $this->channelContext->getToken();

        $firstRequest = $this->createMainRequest();
        $registerController->register($firstRequest, $this->getRegistrationData(), $this->channelContext);

        $winnerToken = $this->channelContext->getToken();
        static::assertSame($winnerToken, $firstRequest->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $duplicateRequest = $this->createMainRequest();
        $duplicateRequest->getSession()->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $consumedToken);

        $duplicateContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create($consumedToken, TestDefaults::CHANNEL);

        $registerController->register($duplicateRequest, $this->getRegistrationData(), $duplicateContext);

        static::assertFalse(
            $duplicateRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT),
            'the duplicate submission must be suppressed instead of registering again'
        );

        static::assertSame($consumedToken, $duplicateRequest->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNull($duplicateRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRejectedRegistrationDoesNotConsumeTheContextToken(): void
    {
        $registerController = static::getContainer()->get(RegisterController::class);

        $incompleteData = $this->getRegistrationData();
        $incompleteData->set('firstName', '');

        $email = (string) $incompleteData->get('email');
        $token = $this->channelContext->getToken();

        $rejectedRequest = $this->createMainRequest();
        $registerController->register($rejectedRequest, $incompleteData, $this->channelContext);

        static::assertFalse($rejectedRequest->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));
        static::assertCount(0, $this->fetchUserIdsByEmail($email));
        static::assertSame($token, $this->channelContext->getToken(), 'a rejected registration must not rotate the token');

        $registerController->register($this->createMainRequest(), $this->getRegistrationData(), $this->channelContext);

        static::assertCount(1, $this->fetchUserIdsByEmail($email));
        static::assertNotSame($token, $this->channelContext->getToken());
    }

    public function testRegisterWithDoubleOptIn(): void
    {
        $container = static::getContainer();

        $userRepository = $container->get('user.repository');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $event = null;
        $this->catchEvent(UserDoubleOptInRegistrationEvent::class, $event);

        $registerController = $this->getRegisterController($container, $systemConfigService, $userRepository);

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        $response = $registerController->register($request, $data, $this->channelContext);

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account/register', $response->getTargetUrl());

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $success = $session->getFlashBag()->get('success');

        static::assertNotCount(0, $success);
        static::assertSame($container->get('translator')->trans('account.optInRegistrationAlert'), $success[0]);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $event);
        static::assertMailEvent(UserDoubleOptInRegistrationEvent::class, $event, $this->channelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($data->all()), $event);

        static::assertStringEndsWith('&redirectTo=frontend.checkout.confirm.page', $event->getConfirmUrl());
    }

    public function testRegisterWithDoubleOptInDomainChanged(): void
    {
        $container = static::getContainer();

        $userRepository = $container->get('user.repository');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);
        $systemConfigService->set('core.loginRegistration.doubleOptInDomain', 'https://test.test.com');

        $event = null;
        $this->catchEvent(UserDoubleOptInRegistrationEvent::class, $event);

        $registerController = $this->getRegisterController($container, $systemConfigService, $userRepository);

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        $response = $registerController->register($request, $data, $this->channelContext);

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account/register', $response->getTargetUrl());

        $session = $request->getSession();
        static::assertInstanceOf(Session::class, $session);
        $success = $session->getFlashBag()->get('success');

        static::assertNotCount(0, $success);
        static::assertSame($container->get('translator')->trans('account.optInRegistrationAlert'), $success[0]);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $event);
        static::assertMailEvent(UserDoubleOptInRegistrationEvent::class, $event, $this->channelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($data->all()), $event);

        static::assertStringStartsWith('https://test.test.com', $event->getConfirmUrl());
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', false);
        $systemConfigService->set('core.loginRegistration.doubleOptInDomain', null);
    }

    public function testConfirmRegisterWithRedirectTo(): void
    {
        $container = static::getContainer();

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = $container->get('user.repository');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $event = null;
        $this->catchEvent(UserDoubleOptInRegistrationEvent::class, $event);

        $registerController = $this->getRegisterController($container, $systemConfigService, $userRepository);

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        $event = null;
        $this->catchEvent(UserDoubleOptInRegistrationEvent::class, $event);

        $registerController->register($request, $data, $this->channelContext);

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $event);

        $user = $userRepository->search(new Criteria([$event->getUserId()]), $this->channelContext->getContext())->getEntities();
        $queryData = new QueryDataBag();
        $queryData->set('redirectTo', 'frontend.checkout.confirm.page');
        $queryData->set('hash', $user->first()?->getHash());
        $queryData->set('em', Hasher::hash($event->getUser()->getEmail(), 'sha1'));

        $response = $registerController->confirmRegistration($this->channelContext, $queryData);

        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/checkout/confirm', $response->getTargetUrl());
    }

    public function testAccountRegisterPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/account/register', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountRegisterPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testUserGroupRegistrationPageLoadedHookScriptsAreExecuted(): void
    {
        $ids = new IdsCollection();
        $this->createUserGroup($ids);

        $response = $this->request('GET', 'user-group-registration/' . $ids->get('group'), []);
        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(UserGroupRegistrationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutRegisterPageLoadedHookScriptsAreExecuted(): void
    {
        $blogNumber = ' p1';

        $this->createBlog(Uuid::randomHex(), $blogNumber);

        $this->request(
            'POST',
            '/checkout/blog/add-by-number',
            $this->tokenize('frontend.checkout.blog.add-by-number', [
                'number' => $blogNumber,
            ])
        );

        $response = $this->request('GET', '/checkout/register', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CheckoutRegisterPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutLoginWithWrongCredentialsStaysOnCheckoutRegister(): void
    {
        $blogNumber = 'test-checkout-login';
        $this->createBlog(Uuid::randomHex(), $blogNumber);

        // Add a blog to the cart, then submit the checkout login form with wrong credentials.
        $this->request(
            'POST',
            '/checkout/blog/add-by-number',
            $this->tokenize('frontend.checkout.blog.add-by-number', ['number' => $blogNumber])
        );

        $response = $this->request(
            'POST',
            '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => 'does-not-exist@example.com',
                'password' => 'wrong-password',
                'redirectTo' => 'frontend.checkout.confirm.page',
            ])
        );

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = (string) $response->getContent();

        // Error shown on the checkout register page, with the login panel expanded.
        static::assertStringContainsString('Could not find an account', $content);
        static::assertStringContainsString('class="collapse show" id="loginCollapse"', $content);
    }

    public function testMismatchedEmailConfirmationShowsErrorOnConfirmationField(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.requireEmailConfirmation', true, $this->getChannelId());

        $data = $this->getRegistrationData()->all();
        $data['emailConfirmation'] = 'erika.musterfrau@example.com';
        $data['errorRoute'] = 'frontend.account.register.page';

        $response = $this->request('POST', '/account/register', $this->tokenize('frontend.account.register.save', $data));

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $crawler = new Crawler((string) $response->getContent());
        static::assertCount(
            1,
            $crawler->filterXPath('//div[@id="personalMailConfirmation-feedback"]/div[contains(@class, "invalid-feedback")]')
        );
    }

    /**
     * @return list<string>
     */
    private function fetchUserIdsByEmail(string $email): array
    {
        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('email', $email));

        return array_values($userRepository->search($criteria, Context::createDefaultContext())->getEntities()->getIds());
    }


    /**
     * @param array<string|int, mixed> $userData
     */
    private function getMailRecipientStruct(array $userData): MailRecipientStruct
    {
        return new MailRecipientStruct([
            (string) $userData['email'] => $userData['firstName'] . ' ' . $userData['lastName'],
        ]);
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getSession());
        $request->request->add(['errorRoute' => 'frontend.checkout.register.page']);
        $request->attributes->add([
            '_route' => 'frontend.checkout.register.page',
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID],
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => TestDefaults::CHANNEL,
        ]);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');

        static::getContainer()->get('request_stack')->push($request);

        return $request;
    }

    private function createMainRequest(): Request
    {
        $requestStack = static::getContainer()->get('request_stack');

        while ($requestStack->getMainRequest() !== null) {
            $requestStack->pop();
        }

        return $this->createRequest();
    }

    private function getRegistrationData(?bool $isGuest = true, string $email = 'max.mustermann@example.com'): RequestDataBag
    {
        $data = [
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
            'email' => $email,
            'emailConfirmation' => $email,
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'frontendUrl' => 'http://localhost',
            'billingAddress' => [
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'countryId' => $this->getValidCountryId(),
                'street' => 'Musterstrasse 13',
                'zipcode' => '48599',
                'city' => 'Epe',
            ],
        ];

        if (!$isGuest) {
            $data['createUserAccount'] = true;
            $data['password'] = TestDefaults::HASHED_PASSWORD;
        }

        return new RequestDataBag($data);
    }

    private function createUserGroup(IdsCollection $ids): void
    {
        $channel = static::getContainer()->get('channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(ChannelEntity::class, $channel);

        static::getContainer()->get('user_group.repository')->create([
            [
                'id' => $ids->create('group'),
                'registrationActive' => true,
                'name' => 'test',
                'registrationChannels' => [
                    [
                        'id' => $channel->getId(),
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function createBlog(string $blogId, string $blogNumber): void
    {
        $taxId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $channel = static::getContainer()->get('channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(ChannelEntity::class, $channel);

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
                    'channelId' => $channel->getId(),
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        static::getContainer()->get('blog.repository')->create([$blog], $context);
    }

    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    private function getRegisterController(
        ContainerInterface $container,
        SystemConfigService $systemConfigService,
        EntityRepository $userRepository
    ): RegisterController {
        return new RegisterController(
            $container->get(AccountLoginPageLoader::class),
            $container->get(RegisterRoute::class),
            $container->get(RegisterConfirmRoute::class),
            $container->get(CartService::class),
            $container->get(CheckoutRegisterPageLoader::class),
            $systemConfigService,
            $userRepository,
            static::createStub(UserGroupRegistrationPageLoader::class),
            $container->get('channel_domain.repository'),
            $container->get(HeaderPageletLoader::class),
            $container->get(FooterPageletLoader::class),
            $container->get(DoubleSubmitGuard::class),
        );
    }
}
