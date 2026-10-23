<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Subscriber\UserRemoteAddressSubscriber;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(UserRemoteAddressSubscriber::class)]
class UserRemoteAddressSubscriberTest extends TestCase
{
    public function testEvents(): void
    {
        static::assertSame([
            UserLoginEvent::class => 'updateRemoteAddressByLogin',
        ], UserRemoteAddressSubscriber::getSubscribedEvents());
    }

    public function testNoRequestThereHappensNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('getBool');

        $subscriber = new UserRemoteAddressSubscriber(
            static::createStub(Connection::class),
            new RequestStack(),
            $configService
        );

        $subscriber->updateRemoteAddressByLogin(new UserLoginEvent(static::createStub(ChannelContext::class), new UserEntity(), 'test'));
    }

    public function testNullIpDoesNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('getBool');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $subscriber = new UserRemoteAddressSubscriber(
            static::createStub(Connection::class),
            $requestStack,
            $configService
        );

        $subscriber->updateRemoteAddressByLogin(new UserLoginEvent(static::createStub(ChannelContext::class), new UserEntity(), 'test'));
    }

    public static function providerIPs(): \Generator
    {
        yield 'enabled, should anonymize' => [
            false,
            '94.31.83.28',
            '94.31.83.0',
        ];

        yield 'enabled, should not anonymize' => [
            true,
            '94.31.83.28',
            '94.31.83.28',
        ];
    }

    #[DataProvider('providerIPs')]
    public function testRequest(bool $anonymize, string $clientIp, string $expectedIp): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->willReturn($anonymize);

        $requestStack = new RequestStack();

        $request = new Request();
        $request->server->set('REMOTE_ADDR', $clientIp);

        $requestStack->push($request);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('update')
            ->with(
                'user',
                ['remote_address' => $expectedIp],
                ['id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL)]
            );

        $subscriber = new UserRemoteAddressSubscriber(
            $connection,
            $requestStack,
            $configService
        );

        $user = new UserEntity();
        $user->setUniqueIdentifier(TestDefaults::CHANNEL);
        $user->setId(TestDefaults::CHANNEL);

        $subscriber->updateRemoteAddressByLogin(new UserLoginEvent(static::createStub(ChannelContext::class), $user, 'test'));
    }
}
