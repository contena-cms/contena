<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Member\DataAbstractionLayer\MemberIndexer;
use Contena\Core\System\Member\DataAbstractionLayer\MemberIndexingMessage;
use Contena\Core\System\Member\Event\MemberRegisterEvent;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberEvents;
use Contena\Core\System\Member\Subscriber\MemberFlowEventsSubscriber;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MemberFlowEventsSubscriber::class)]
class MemberFlowEventsSubscriberTest extends TestCase
{
    private Stub&EventDispatcherInterface $dispatcher;

    private Stub&ChannelContextRestorer $restorer;

    private Stub&MemberIndexer $memberIndexer;

    private IdsCollection $ids;

    private MemberFlowEventsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);
        $this->restorer = static::createStub(ChannelContextRestorer::class);
        $this->memberIndexer = static::createStub(MemberIndexer::class);
        $this->subscriber = $this->buildSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            MemberEvents::MEMBER_WRITTEN_EVENT => 'onMemberWritten',
        ], $this->subscriber->getSubscribedEvents());
    }

    public function testOnMemberWrittenWithChannelApiSource(): void
    {
        $context = Context::createDefaultContext(new ChannelApiSource('channel-id'));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->once())
            ->method('getContext')
            ->willReturn($context);
        $event->expects($this->never())->method('getPayloads');

        $this->subscriber->onMemberWritten($event);
    }

    public function testOnMemberWrittenRethrowsOtherChannelErrors(): void
    {
        $this->expectExceptionObject(ChannelException::channelNotFound('channel-id'));

        $event = $this->createWrittenEvent($this->ids->get('memberId'));

        $restorer = $this->createMock(ChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByMember')
            ->willThrowException(ChannelException::channelNotFound('channel-id'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->buildSubscriber($dispatcher, $restorer)->onMemberWritten($event);
    }

    public function testOnMemberCreatedWithoutMemberInContext(): void
    {
        $memberId = $this->ids->get('memberId');
        $event = $this->createWrittenEvent($memberId);

        $memberIndexer = $this->createMock(MemberIndexer::class);
        $memberIndexer->expects($this->once())
            ->method('handle')
            ->with(new MemberIndexingMessage([$memberId]));

        $restorer = $this->createMock(ChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByMember')
            ->willReturn(Generator::generateChannelContext());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->buildSubscriber($dispatcher, $restorer, $memberIndexer)->onMemberWritten($event);
    }

    public function testOnMemberCreatedWithMember(): void
    {
        $memberId = $this->ids->get('memberId');
        $event = $this->createWrittenEvent($memberId);

        $memberIndexer = $this->createMock(MemberIndexer::class);
        $memberIndexer->expects($this->once())
            ->method('handle')
            ->with(new MemberIndexingMessage([$memberId]));

        $member = new MemberEntity();
        $channelContext = Generator::generateChannelContext(member: $member);
        $restorer = $this->createMock(ChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByMember')
            ->willReturn($channelContext);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new MemberRegisterEvent($channelContext, $member));

        $this->buildSubscriber($dispatcher, $restorer, $memberIndexer)->onMemberWritten($event);
    }

    private function createWrittenEvent(string $memberId): EntityWrittenEvent
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());
        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn([[
                'createdAt' => new \DateTime()->format(\DATE_ATOM),
                'id' => $memberId,
            ]]);

        return $event;
    }

    private function buildSubscriber(
        ?EventDispatcherInterface $dispatcher = null,
        ?ChannelContextRestorer $restorer = null,
        ?MemberIndexer $memberIndexer = null,
    ): MemberFlowEventsSubscriber {
        return new MemberFlowEventsSubscriber(
            $dispatcher ?? $this->dispatcher,
            $restorer ?? $this->restorer,
            $memberIndexer ?? $this->memberIndexer,
            static::createStub(Connection::class),
        );
    }
}
