<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\Channel\AbstractLogoutRoute;
use Contena\Core\System\Member\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\System\Member\Channel\ImitateMemberRoute;
use Contena\Core\System\Member\Channel\LoginRoute;
use Contena\Core\System\Member\Channel\ResetPasswordRoute;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\AuthController;
use Contena\Frontend\Framework\Routing\ClearSiteDataListener;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * @internal
 */
class AuthControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;
    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            self::DEFAULT_WEB_CHANNEL_ID,
        );
    }

    public function testRedirectToAccountPageAfterLogin(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/login');
        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account', $response->getTargetUrl());
    }

    public function testLogoutSendsNoClearSiteDataHeaderByDefault(): void
    {
        $browser = $this->login();
        $browser->request('GET', '/account/logout');

        static::assertSame(302, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertFalse($browser->getResponse()->headers->has('Clear-Site-Data'));
    }

    public function testLogoutSendsClearSiteDataWhenDirectivesAreConfigured(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $onResponse = new ClearSiteDataListener(['cache', 'storage'])->onResponse(...);
        $dispatcher->addListener(FrontendRouteScope::ID . '.scope.response', $onResponse);

        try {
            $browser = $this->login();
            $browser->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
            $browser->setServerParameter('HTTP_SEC_FETCH_MODE', 'navigate');
            $browser->setServerParameter('HTTP_SEC_FETCH_DEST', 'document');
            $browser->request('GET', '/account/logout');

            static::assertSame(302, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
            static::assertSame('"cache", "storage"', $browser->getResponse()->headers->get('Clear-Site-Data'));
        } finally {
            $dispatcher->removeListener(FrontendRouteScope::ID . '.scope.response', $onResponse);
        }
    }

    public function testAccountLoginInactiveMember(): void
    {
        $this->createMember(active: false);
        $request = $this->createRequest('frontend.account.login.page');
        $request->attributes->add(['username' => 'test@example.com', 'password' => 'test12345']);
        static::getContainer()->get('request_stack')->push($request);

        $response = $this->getAuthController()->login(
            $request,
            new RequestDataBag($request->attributes->all()),
            $this->channelContext,
        );

        static::assertSame(200, $response->getStatusCode());
    }

    public function testAccountRecoveryPasswordWrongHash(): void
    {
        $request = $this->createRequest('frontend.account.recover.password.page', ['hash' => 'wrong']);
        static::getContainer()->get('request_stack')->push($request);

        $response = $this->getAuthController()->resetPasswordForm($request, $this->channelContext);
        $flashBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        static::assertSame(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertSame('/account/recover', $response->headers->get('location') ?? '');
    }

    public function testAccountRecoveryPasswordNoHash(): void
    {
        $request = $this->createRequest('frontend.account.recover.password.page');
        static::getContainer()->get('request_stack')->push($request);

        $response = $this->getAuthController()->resetPasswordForm($request, $this->channelContext);
        $flashBag = $this->getSession()->getBag('flashes');
        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        static::assertSame(302, $response->getStatusCode());
        static::assertCount(1, $flashBag->get('danger'));
        static::assertSame('/account/recover', $response->headers->get('location') ?? '');
    }

    private function login(): KernelBrowser
    {
        $member = $this->createMember();
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('POST', EnvironmentHelper::getVariable('APP_URL') . '/account/login', $this->tokenize('frontend.account.login', [
            'username' => $member->getEmail(),
            'password' => 'test12345',
        ]));

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        return $browser;
    }

    private function createMember(bool $active = true): MemberEntity
    {
        $memberId = Uuid::randomHex();

        /** @var EntityRepository<MemberCollection> $repository */
        $repository = static::getContainer()->get('member.repository');
        $repository->create([[
            'id' => $memberId,
            'channelId' => self::DEFAULT_WEB_CHANNEL_ID,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => 'test@example.com',
            'password' => 'test12345',
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => $active,
        ]], Context::createDefaultContext());

        $member = $repository->search(new Criteria([$memberId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberEntity::class, $member);

        return $member;
    }

    private function getAuthController(?AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute = null): AuthController
    {
        $controller = new AuthController(
            static::getContainer()->get(AccountLoginPageLoader::class),
            $sendPasswordRecoveryMailRoute ?? $this->createMock(AbstractSendPasswordRecoveryMailRoute::class),
            static::getContainer()->get(ResetPasswordRoute::class),
            static::getContainer()->get(LoginRoute::class),
            $this->createMock(AbstractLogoutRoute::class),
            static::getContainer()->get(ImitateMemberRoute::class),
            static::getContainer()->get(AccountRecoverPasswordPageLoader::class),
        );
        $controller->setContainer(static::getContainer());

        return $controller;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createRequest(string $route, array $params = []): Request
    {
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            self::DEFAULT_WEB_CHANNEL_ID,
        );

        $request = Request::create((string) EnvironmentHelper::getVariable('APP_URL'));
        $request->query->add($params);
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
