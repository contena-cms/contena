<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Event\AppActivatedEvent;
use Contena\Core\Framework\App\Event\AppDeactivatedEvent;
use Contena\Core\Framework\App\Event\AppDeletedEvent;
use Contena\Core\Framework\App\Event\AppInstalledEvent;
use Contena\Core\Framework\App\Event\AppUpdatedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Contena\Core\Framework\Mcp\Notification\AppMcpCapabilityLifecycleSubscriber;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpCapabilityLifecycleSubscriber::class)]
class AppMcpCapabilityLifecycleSubscriberTest extends TestCase
{
    public function testSubscribesToAppLifecycleEvents(): void
    {
        static::assertSame([
            AppActivatedEvent::class => 'onAppChanged',
            AppDeactivatedEvent::class => 'onAppChanged',
            AppDeletedEvent::class => 'onAppDeleted',
            AppInstalledEvent::class => 'onAppInstalledOrUpdated',
            AppUpdatedEvent::class => 'onAppInstalledOrUpdated',
        ], AppMcpCapabilityLifecycleSubscriber::getSubscribedEvents());
    }

    public function testNotifiesAllCapabilityTypesOnInstallAndUpdate(): void
    {
        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with(new McpListChangedNotificationSet(tools: true, resources: true, prompts: true));

        $subscriber = new AppMcpCapabilityLifecycleSubscriber(static::createStub(AppMcpCapabilityDetector::class), $notifier);
        $subscriber->onAppInstalledOrUpdated();
    }

    public function testNotifiesPersistedCapabilitiesForChangedApp(): void
    {
        $appId = Uuid::randomHex();
        $notifications = new McpListChangedNotificationSet(tools: true, resources: false, prompts: true);

        $detector = $this->createMock(AppMcpCapabilityDetector::class);
        $detector->expects($this->once())
            ->method('persistedForApp')
            ->with($appId)
            ->willReturn($notifications);

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($notifications);

        $subscriber = new AppMcpCapabilityLifecycleSubscriber($detector, $notifier);
        $subscriber->onAppChanged(new AppActivatedEvent(AppFixture::createAppEntity(id: $appId), Context::createDefaultContext()));
    }

    public function testNotifiesPersistedCapabilitiesForDeletedApp(): void
    {
        $appId = Uuid::randomHex();
        $notifications = new McpListChangedNotificationSet(tools: false, resources: true, prompts: false);

        $detector = $this->createMock(AppMcpCapabilityDetector::class);
        $detector->expects($this->once())
            ->method('persistedForApp')
            ->with($appId)
            ->willReturn($notifications);

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with($notifications);

        $subscriber = new AppMcpCapabilityLifecycleSubscriber($detector, $notifier);
        $subscriber->onAppDeleted(new AppDeletedEvent($appId, Context::createDefaultContext()));
    }
}
