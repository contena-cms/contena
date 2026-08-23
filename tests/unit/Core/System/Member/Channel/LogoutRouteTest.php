<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Channel\LogoutRoute;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(LogoutRoute::class)]
class LogoutRouteTest extends TestCase
{
    public function testLogoutDeletesMemberContextAndReturnsAnonymousToken(): void
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $member->setName('Test Member');
        $member->setEmail('member@example.com');

        $loggedInContext = $this->createContext('member-token', $member);
        $anonymousContext = $this->createContext('anonymous-token');

        $persister = $this->createMock(ChannelContextPersister::class);
        $persister->expects($this->once())
            ->method('delete')
            ->with('member-token', $loggedInContext->getChannelId());

        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->willReturn($anonymousContext);

        $eventCalled = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MemberLogoutEvent::class, static function (MemberLogoutEvent $event) use ($member, $anonymousContext, &$eventCalled): void {
            $eventCalled = true;
            static::assertSame($member, $event->getMember());
            static::assertSame($anonymousContext, $event->getChannelContext());
        });

        $route = new LogoutRoute($persister, $dispatcher, $contextService);
        $response = $route->logout($loggedInContext, new RequestDataBag());

        static::assertSame('anonymous-token', $response->getToken());
        static::assertTrue($eventCalled);
    }

    private function createContext(string $token, ?MemberEntity $member = null): ChannelContext
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $group = new MemberGroupEntity();
        $group->setId(Uuid::randomHex());

        return Generator::generateChannelContext(
            token: $token,
            channel: $channel,
            currentMemberGroup: $group,
            member: $member,
        );
    }
}
