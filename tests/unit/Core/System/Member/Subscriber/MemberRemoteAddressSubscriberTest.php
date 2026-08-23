<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Subscriber\MemberRemoteAddressSubscriber;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(MemberRemoteAddressSubscriber::class)]
class MemberRemoteAddressSubscriberTest extends TestCase
{
    public function testEvents(): void
    {
        static::assertSame([
            MemberLoginEvent::class => 'updateRemoteAddressByLogin',
        ], MemberRemoteAddressSubscriber::getSubscribedEvents());
    }

    public function testNoRequestThereHappensNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('getBool');

        $subscriber = new MemberRemoteAddressSubscriber(
            static::createStub(Connection::class),
            new RequestStack(),
            $configService,
        );

        $subscriber->updateRemoteAddressByLogin(new MemberLoginEvent(
            Generator::generateChannelContext(),
            new MemberEntity(),
            'test',
        ));
    }

    public function testNullIpDoesNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('getBool');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $subscriber = new MemberRemoteAddressSubscriber(
            static::createStub(Connection::class),
            $requestStack,
            $configService,
        );

        $subscriber->updateRemoteAddressByLogin(new MemberLoginEvent(
            Generator::generateChannelContext(),
            new MemberEntity(),
            'test',
        ));
    }
}
