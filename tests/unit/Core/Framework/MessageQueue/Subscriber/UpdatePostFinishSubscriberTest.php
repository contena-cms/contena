<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\MessageQueue\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Contena\Core\Framework\MessageQueue\Subscriber\UpdatePostFinishSubscriber;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;

/**
 * @internal
 */
#[CoversClass(UpdatePostFinishSubscriber::class)]
class UpdatePostFinishSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = UpdatePostFinishSubscriber::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertArrayHasKey(UpdatePostFinishEvent::class, $events);
        static::assertSame('updatePostFinishEvent', $events[UpdatePostFinishEvent::class]);
    }

    public function testUpdatePostFinishEvent(): void
    {
        $registry = $this->createMock(TaskRegistry::class);
        $registry->expects($this->once())->method('registerTasks');

        new UpdatePostFinishSubscriber($registry)->updatePostFinishEvent();
    }
}
