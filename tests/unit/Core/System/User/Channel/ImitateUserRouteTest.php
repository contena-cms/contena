<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\ImitateUserRoute;
use Contena\Core\System\User\Channel\LogoutRoute;
use Contena\Core\System\User\Extension\ImitateUserRouteExtension;
use Contena\Core\System\User\ImitateUserTokenGenerator;
use Contena\Core\System\User\Struct\ImitateUserToken;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ImitateUserRoute::class)]
class ImitateUserRouteTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testImitateUserOld(): void
    {
        $userId = Uuid::randomHex();
        $adminUserId = Uuid::randomHex();
        $token = 'testToken';

        $imitateUserTokenGenerator = $this->createMock(ImitateUserTokenGenerator::class);
        $imitateUserTokenGenerator
            ->expects($this->once())
            ->method('validate')
            ->with($token, TestDefaults::CHANNEL, $userId, $adminUserId);

        $accountService = static::createStub(AccountService::class);
        $accountService->method('loginById')->willReturn('newToken');

        $route = new ImitateUserRoute(
            $accountService,
            $imitateUserTokenGenerator,
            static::createStub(LogoutRoute::class),
            static::createStub(ChannelContextFactory::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn(TestDefaults::CHANNEL);

        $dataBag = new RequestDataBag([
            ImitateUserRoute::TOKEN => $token,
            ImitateUserRoute::CUSTOMER_ID => $userId,
            ImitateUserRoute::USER_ID => $adminUserId,
        ]);

        $response = $route->imitateUserLogin($dataBag, $channelContext);

        static::assertSame('newToken', $response->getToken());
    }

    public function testImitateUser(): void
    {
        $token = 'testToken';
        $tokenStruct = new ImitateUserToken();
        $tokenStruct->userId = Uuid::randomHex();
        $tokenStruct->iss = Uuid::randomHex();
        $tokenStruct->channelId = TestDefaults::CHANNEL;
        $channelContext = Generator::generateChannelContext();
        $channelContext->assign(['user' => null]);

        $imitateUserTokenGenerator = $this->createMock(ImitateUserTokenGenerator::class);
        $imitateUserTokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with($token)
            ->willReturn($tokenStruct);

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with($tokenStruct->userId, $channelContext)
            ->willReturn('newToken');

        $route = new ImitateUserRoute(
            $accountService,
            $imitateUserTokenGenerator,
            static::createStub(LogoutRoute::class),
            static::createStub(ChannelContextFactory::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $dataBag = new RequestDataBag([
            ImitateUserRoute::TOKEN => $token,
        ]);

        $response = $route->imitateUserLogin($dataBag, $channelContext);

        static::assertSame('newToken', $response->getToken());
    }

    public function testImitateUserWithLoggedInUser(): void
    {
        $token = 'testToken';
        $tokenStruct = new ImitateUserToken();
        $tokenStruct->userId = Uuid::randomHex();
        $tokenStruct->iss = Uuid::randomHex();
        $tokenStruct->channelId = TestDefaults::CHANNEL;
        $channelContext = Generator::generateChannelContext();

        $imitateUserTokenGenerator = $this->createMock(ImitateUserTokenGenerator::class);
        $imitateUserTokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with($token)
            ->willReturn($tokenStruct);

        $channelContextFactory = $this->createMock(ChannelContextFactory::class);
        $channelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with('loggedOutToken', TestDefaults::CHANNEL)
            ->willReturn($channelContext);

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with($tokenStruct->userId, $channelContext)
            ->willReturn('newToken');

        $logoutRoute = $this->createMock(LogoutRoute::class);
        $logoutRoute
            ->expects($this->once())
            ->method('logout')
            ->willReturn(new ContextTokenResponse('loggedOutToken'));

        $route = new ImitateUserRoute(
            $accountService,
            $imitateUserTokenGenerator,
            $logoutRoute,
            $channelContextFactory,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $dataBag = new RequestDataBag([
            ImitateUserRoute::TOKEN => $token,
        ]);

        $response = $route->imitateUserLogin($dataBag, $channelContext);

        static::assertSame('newToken', $response->getToken());
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateChannelContext();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('imitate-user-route.imitate-user-login.pre', static function (ImitateUserRouteExtension $extension) use ($data, $context, $response): void {
            static::assertSame(['data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ImitateUserRoute(
            static::createStub(AccountService::class),
            static::createStub(ImitateUserTokenGenerator::class),
            static::createStub(LogoutRoute::class),
            static::createStub(ChannelContextFactory::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->imitateUserLogin($data, $context));
    }
}
