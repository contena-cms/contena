<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Member\Subscriber\MemberTokenSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;

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
        $subscriber = new MemberTokenSubscriber($contextPersister, new RequestStack());
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
        $subscriber = new MemberTokenSubscriber($contextPersister, new RequestStack());
        $event = new EntityDeletedEvent('member', [
            new EntityWriteResult('deleted-member', [], 'member', EntityWriteResult::OPERATION_DELETE),
        ], Context::createDefaultContext());

        $subscriber->onMemberDeleted($event);
    }
}
