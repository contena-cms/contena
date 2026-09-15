<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Subscriber;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Member\Subscriber\MemberTokenSubscriber;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(MemberTokenSubscriber::class)]
class MemberTokenSubscriberTest extends TestCase
{
    public function testOnlyPasswordUpdatesRevokeMemberTokens(): void
    {
        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister->expects($this->once())
            ->method('revokeAllMemberTokens')
            ->with('updated-password');
        $subscriber = new MemberTokenSubscriber($contextPersister, new RequestStack(), $this->sessionContextToken());
        $event = new EntityWrittenEvent('member', [
            new EntityWriteResult('inserted', ['id' => 'inserted', 'password' => 'hash'], 'member', EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult('updated-email', ['id' => 'updated-email', 'email' => 'new@example.com'], 'member', EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult('updated-password', ['id' => 'updated-password', 'password' => 'hash'], 'member', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext());

        $subscriber->onMemberWritten($event);
    }

    public function testDeletedMemberTokensAreRevoked(): void
    {
        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister->expects($this->once())
            ->method('revokeAllMemberTokens')
            ->with('deleted-member');
        $subscriber = new MemberTokenSubscriber($contextPersister, new RequestStack(), $this->sessionContextToken());
        $event = new EntityDeletedEvent('member', [
            new EntityWriteResult('deleted-member', [], 'member', EntityWriteResult::OPERATION_DELETE),
        ], Context::createDefaultContext());

        $subscriber->onMemberDeleted($event);
    }

    public function testAPasswordChangeRotatesTheFrontendSessionAndKeepsTheNewToken(): void
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getMemberId')->willReturn('member-id');
        $context->method('getToken')->willReturn('old-token');
        $context->method('getChannelId')->willReturn('channel-id');

        $request = new Request(attributes: [
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
        ]);
        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'old-token');
        $request->setSession($session);

        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister->method('replace')->willReturn('new-token');
        $contextPersister->expects($this->once())
            ->method('revokeAllMemberTokens')
            ->with('member-id', 'new-token');

        $subscriber = new MemberTokenSubscriber($contextPersister, new RequestStack([$request]), $this->sessionContextToken());
        $subscriber->onMemberWritten(new EntityWrittenEvent('member', [
            new EntityWriteResult('member-id', ['id' => 'member-id', 'password' => 'hash'], 'member', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext()));

        static::assertSame('new-token', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('new-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function sessionContextToken(): SessionContextTokenAccessor
    {
        return new SessionContextTokenAccessor([], true, new StaticSystemConfigService(), new RouteScopeRegistry([]));
    }
}
