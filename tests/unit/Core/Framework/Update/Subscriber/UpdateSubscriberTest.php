<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Update\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\Framework\Update\Subscriber\UpdateSubscriber;

/**
 * @internal
 */
#[CoversClass(UpdateSubscriber::class)]
class UpdateSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                UpdatePostFinishEvent::class => [
                    ['updateFinishedDone', -9999],
                ],
            ],
            UpdateSubscriber::getSubscribedEvents()
        );
    }

    public function testUpdateSuccessful(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('admin_userId123', 'integrationId321'));
        $version = '6.0.1_test';

        $notificationServiceMock = $this->createMock(NotificationService::class);
        $notificationServiceMock
            ->expects($this->once())
            ->method('createNotification')
            ->willReturnCallback(static function ($data): void {
                static::assertSame('something to inform' . \PHP_EOL, $data['message']);
            });

        $event = new UpdatePostFinishEvent($context, $version, $version);
        $event->appendPostUpdateMessage('something to inform');

        $updateSubscriber = new UpdateSubscriber($notificationServiceMock);

        $updateSubscriber->updateFinishedDone($event);
    }

    public function testUpdateWithoutMessageGetsSkipped(): void
    {
        $context = Context::createDefaultContext();
        $version = '6.0.1_test';

        $notificationServiceMock = $this->createMock(NotificationService::class);
        $notificationServiceMock->expects($this->never())->method('createNotification');

        $event = new UpdatePostFinishEvent($context, $version, $version);

        $updateSubscriber = new UpdateSubscriber($notificationServiceMock);

        $updateSubscriber->updateFinishedDone($event);
    }
}
