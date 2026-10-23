<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\LoginRoute;
use Contena\Core\System\User\Channel\LogoutRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class LogoutRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);
    }

    public function testNotLoggedin(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
    }

    public function testValidLogout(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/user'
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testLogoutKeepsCartToBeAbleToRestore(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $tokens = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT token FROM channel_api_context WHERE user_id =  (SELECT id FROM user where email = ?)', [$email]);

        static::assertCount(1, $tokens);
        static::assertNotContains($contextToken, $tokens, 'Old token should still exist');
    }

    public function testLoggedOutKeepUserContextWithoutReplaceTokenParameter(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        $currentUserToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?: '';

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $currentUserToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        $userIdWithOldToken = static::getContainer()->get(Connection::class)->fetchOne('SELECT user_id FROM channel_api_context WHERE token = ?', [$currentUserToken]);
        static::assertFalse($userIdWithOldToken, 'The old token should be gone');
    }

    public function testLogoutRouteReturnContextTokenResponse(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $contextToken = Random::getAlphanumericString(32);

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::CHANNEL,
            []
        );

        $request = new RequestDataBag(['email' => $email, 'password' => 'contena']);
        $loginResponse = static::getContainer()->get(LoginRoute::class)->login($request, $channelContext);

        $userId = $this->createUser();
        $user = static::getContainer()
            ->get('user.repository')
            ->search(new Criteria(), Context::createDefaultContext())->getEntities()
            ->get($userId);
        static::assertInstanceOf(UserEntity::class, $user);
        $user->setGuest(false);
        $channelContext->assign([
            'token' => $loginResponse->getToken(),
            'user' => $user,
        ]);

        $logoutResponse = static::getContainer()->get(LogoutRoute::class)->logout(
            $channelContext,
            new RequestDataBag()
        );

        static::assertInstanceOf(ContextTokenResponse::class, $logoutResponse);
        static::assertNotSame($loginResponse->getToken(), $logoutResponse->getToken());
    }

    public function testLogoutForcedForGuestAccounts(): void
    {
        $config = static::getContainer()->get(SystemConfigService::class);
        $config->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $context = static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, []);

        $request = new RequestDataBag(['email' => $email, 'password' => 'contena']);
        $login = static::getContainer()
            ->get(LoginRoute::class)
            ->login($request, $context);

        $userId = $this->createUser();
        $user = static::getContainer()
            ->get('user.repository')
            ->search(new Criteria(), Context::createDefaultContext())->getEntities()
            ->get($userId);
        static::assertInstanceOf(UserEntity::class, $user);
        $user->setGuest(true);
        $context->assign([
            'token' => $login->getToken(),
            'user' => $user,
        ]);

        $logout = static::getContainer()
            ->get(LogoutRoute::class)
            ->logout($context, $request);

        static::assertInstanceOf(ContextTokenResponse::class, $logout);
        static::assertNotSame($login->getToken(), $logout->getToken());

        $exists = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM channel_api_context WHERE token = :token', ['token' => $login->getToken()]);

        static::assertCount(0, $exists);
    }

    public function testValidLogoutAsGuest(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email, true);
        $this->browser->setServerParameter(
            'HTTP_SW_CONTEXT_TOKEN',
            $this->getLoggedInContextToken($userId, $this->ids->get('sales-channel'))
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        static::assertSame(
            200,
            $this->browser->getResponse()->getStatusCode(),
            $this->browser->getResponse()->getContent()
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/user'
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }
}
