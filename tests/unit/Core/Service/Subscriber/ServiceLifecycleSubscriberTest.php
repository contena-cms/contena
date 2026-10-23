<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Service\Event\NewServicesInstalledEvent;
use Contena\Core\Service\Notification;
use Contena\Core\Service\Subscriber\ServiceLifecycleSubscriber;

/**
 * @internal
 */
#[CoversClass(ServiceLifecycleSubscriber::class)]
class ServiceLifecycleSubscriberTest extends TestCase
{
    public function testSubscribesToCorrectEvents(): void
    {
        static::assertSame([
            NewServicesInstalledEvent::class => 'sendInstalledNotification',
        ], ServiceLifecycleSubscriber::getSubscribedEvents());
    }

    public function testDelegatesAllServicesInstalledEvents(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())->method('createNotification');

        $subscriber = new ServiceLifecycleSubscriber(new Notification($notificationService));
        $subscriber->sendInstalledNotification(new NewServicesInstalledEvent());
    }
}
