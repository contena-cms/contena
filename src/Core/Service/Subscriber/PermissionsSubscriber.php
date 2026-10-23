<?php declare(strict_types=1);

namespace Contena\Core\Service\Subscriber;

use Contena\Core\Service\Event\PermissionsGrantedEvent;
use Contena\Core\Service\Event\PermissionsRevokedEvent;
use Contena\Core\Service\ServiceLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
readonly class PermissionsSubscriber implements EventSubscriberInterface
{
    public function __construct(private ServiceLifecycle $serviceLifecycle)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsGrantedEvent::class => 'reevaluateServices',
            PermissionsRevokedEvent::class => 'reevaluateServices',
        ];
    }

    public function reevaluateServices(PermissionsGrantedEvent|PermissionsRevokedEvent $event): void
    {
        $this->serviceLifecycle->reevaluateInstalled($event->getContext());
    }
}
