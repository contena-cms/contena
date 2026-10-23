<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\Service\WebhookManager;
use Contena\Core\Framework\Webhook\WebhookDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[CoversClass(WebhookDispatcher::class)]
class WebhookDispatcherTest extends TestCase
{
    public function testDispatchDispatchesToInnerAndManager(): void
    {
        $e = new TestEvent();

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($e, 'event')
            ->willReturnArgument(0);

        $webhookManager = $this->createMock(WebhookManager::class);
        $webhookManager->expects($this->once())->method('dispatch')->with($e);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcher,
            $webhookManager,
        );

        $webhookDispatcher->dispatch($e, 'event');
    }

    public function testDispatchReturnsSameEventAsDispatched(): void
    {
        $e = new TestEvent();

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($e, 'event')
            ->willReturnArgument(0);

        $webhookManager = $this->createMock(WebhookManager::class);
        $webhookManager->expects($this->once())->method('dispatch')->with($e);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcher,
            $webhookManager,
        );

        static::assertSame(
            $e,
            $webhookDispatcher->dispatch($e, 'event')
        );
    }

    public function testAddSubscriberForwardsToInner(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [];
            }
        };

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->once())
            ->method('addSubscriber')
            ->with($subscriber);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            static::createStub(WebhookManager::class),
        );

        $webhookDispatcher->addSubscriber($subscriber);
    }

    public function testRemoveSubscriberForwardsToInner(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [];
            }
        };

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->once())
            ->method('removeSubscriber')
            ->with($subscriber);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            static::createStub(WebhookManager::class),
        );

        $webhookDispatcher->removeSubscriber($subscriber);
    }

    public function testAddListenerForwardsToInner(): void
    {
        $listener = static function (): void {};

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->once())
            ->method('addListener')
            ->with('event', $listener, 5);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            static::createStub(WebhookManager::class),
        );

        $webhookDispatcher->addListener('event', $listener, 5);
    }

    public function testRemoveListenerForwardsToInner(): void
    {
        $listener = static function (): void {};

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->once())
            ->method('removeListener')
            ->with('event', $listener);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            static::createStub(WebhookManager::class),
        );

        $webhookDispatcher->removeListener('event', $listener);
    }

    public function testGetListenersForwardsToInner(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->once())
            ->method('getListeners')
            ->with('event');

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            static::createStub(WebhookManager::class),
        );

        $webhookDispatcher->getListeners('event');
    }

    public function testGetListenerPriorityForwardsToInner(): void
    {
        $listener = static function (): void {};

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->once())
            ->method('getListenerPriority')
            ->with('event', $listener);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            static::createStub(WebhookManager::class),
        );

        $webhookDispatcher->getListenerPriority('event', $listener);
    }

    public function testHasListenersForwardsToInner(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->once())
            ->method('hasListeners')
            ->with('event');

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            static::createStub(WebhookManager::class),
        );

        $webhookDispatcher->hasListeners('event');
    }
}

/**
 * @internal
 */
class TestEvent implements Hookable
{
    public function getName(): string
    {
        return 'test';
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
