<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\User\Subscriber\UserTokenSubscriber;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(UserTokenSubscriber::class)]
class UserTokenSubscriberTest extends TestCase
{
    public function testOnlyPasswordUpdatesRevokeUserTokens(): void
    {
        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with('updated-password');
        $subscriber = new UserTokenSubscriber($contextPersister, new RequestStack(), $this->sessionContextToken());
        $context = Context::createDefaultContext();
        $event = new EntityWrittenEvent('user', [
        ], $context);

        $subscriber->onUserWritten($event);
    }

    public function testDeletedUserTokensAreRevoked(): void
    {
        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with('deleted-user');
        $subscriber = new UserTokenSubscriber($contextPersister, new RequestStack(), $this->sessionContextToken());
        $event = new EntityDeletedEvent('user', [
        ], Context::createDefaultContext());

        $subscriber->onUserDeleted($event);
    }

    public function testAPasswordChangeRotatesTheFrontendSessionAndKeepsTheNewToken(): void
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getUserId')->willReturn('user-id');
        $context->method('getToken')->willReturn('old-token');
        $context->method('getChannelId')->willReturn('sales-channel-id');

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
            ->method('revokeAllUserTokens')
            ->with('user-id', 'new-token');

        $subscriber = new UserTokenSubscriber($contextPersister, new RequestStack([$request]), $this->sessionContextToken());
        $subscriber->onUserWritten(new EntityWrittenEvent('user', [
        ], Context::createDefaultContext()));

        static::assertSame('new-token', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('new-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function sessionContextToken(): SessionContextTokenAccessor
    {
        return new SessionContextTokenAccessor([], new StaticSystemConfigService());
    }
}
