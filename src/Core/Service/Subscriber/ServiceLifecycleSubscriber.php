<?php declare(strict_types=1);

namespace Contena\Core\Service\Subscriber;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\Event\NewServicesInstalledEvent;
use Contena\Core\Service\Notification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
readonly class ServiceLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(private Notification $notification)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            NewServicesInstalledEvent::class => 'sendInstalledNotification',
        ];
    }

    public function sendInstalledNotification(NewServicesInstalledEvent $event): void
    {
        $this->notification->newServicesInstalled();
    }
}
