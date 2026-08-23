<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\MessageQueue\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * @internal
 */
#[CoversClass(MessageQueueStatsSubscriber::class)]
class MessageQueueStatsSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            WorkerMessageHandledEvent::class => 'onMessageHandled',
        ], MessageQueueStatsSubscriber::getSubscribedEvents());
    }

    public function testOnMessageHandledRegistersStats(): void
    {
        $envelope = new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@1726567204')),
        ]);
        $statsService = $this->createMock(StatsService::class);
        $statsService->expects($this->once())
            ->method('registerMessage')
            ->with($envelope);

        $subscriber = new MessageQueueStatsSubscriber($statsService);
        $subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'theReceiver'));
    }
}
