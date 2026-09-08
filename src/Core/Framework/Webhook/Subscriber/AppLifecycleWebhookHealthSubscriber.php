<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Subscriber;

use Contena\Core\Framework\App\Event\AppActivatedEvent;
use Contena\Core\Framework\App\Event\AppDeactivatedEvent;
use Contena\Core\Framework\App\Event\AppInstalledEvent;
use Contena\Core\Framework\App\Event\AppUpdatedEvent;
use Contena\Core\Framework\App\Event\ManifestChangedEvent;
use Contena\Core\Framework\Webhook\Service\WebhookHealthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An app install or update is a clean slate for its webhooks' health; time spent deactivated must
 * not count toward the suspension bound.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Framework\Webhook\Subscriber\ReactivateWebhooksOnAppReregistrationSubscriberTest
 * @see \Contena\Tests\Integration\Core\Framework\Webhook\Health\WebhookHealthTickTest
 */
class AppLifecycleWebhookHealthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WebhookHealthService $healthService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'reactivate',
            AppUpdatedEvent::class => 'reactivate',
            AppDeactivatedEvent::class => 'pauseSuspensionClock',
            AppActivatedEvent::class => 'resumeSuspensionClock',
        ];
    }

    public function reactivate(ManifestChangedEvent $event): void
    {
        $this->healthService->reactivateForApp($event->getApp()->getId());
    }

    public function pauseSuspensionClock(AppDeactivatedEvent $event): void
    {
        $this->healthService->pauseSuspensionClockForApp($event->getApp()->getId());
    }

    public function resumeSuspensionClock(AppActivatedEvent $event): void
    {
        $this->healthService->resumeSuspensionClockForApp($event->getApp()->getId());
    }
}
