<?php declare(strict_types=1);

namespace Contena\Core\Service\Subscriber;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Event\ContenaAccountLoginEvent;
use Contena\Core\Framework\Store\Event\ContenaAccountLogoutEvent;
use Contena\Core\Service\ServiceLifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class ContenaAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ServiceLifecycle $serviceLifecycle,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ContenaAccountLoginEvent::class => 'reevaluateServices',
            ContenaAccountLogoutEvent::class => 'reevaluateServices',
        ];
    }

    public function reevaluateServices(ContenaAccountLoginEvent|ContenaAccountLogoutEvent $event): void
    {
        $this->serviceLifecycle->reevaluateInstalled($event->getContext());
    }
}
