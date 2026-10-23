<?php declare(strict_types=1);

namespace Contena\Core\Service\Subscriber;

use Contena\Core\Framework\Update\Event\ExtensionCompatibilitiesResolvedEvent;
use Contena\Core\Framework\Update\Services\ExtensionCompatibility;
use Contena\Core\Service\ServiceRegistry\Client;
use Contena\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ExtensionCompatibilitiesResolvedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $serviceRegistryClient
    ) {
    }



    public static function getSubscribedEvents(): array
    {
        return [
            ExtensionCompatibilitiesResolvedEvent::class => 'markAppsWithServiceAsCompatible',
        ];
    }

    public function markAppsWithServiceAsCompatible(ExtensionCompatibilitiesResolvedEvent $event): void
    {
        $services = $this->serviceRegistryClient->getAll();
        $serviceNames = array_map(static fn (ServiceEntry $entry) => $entry->name, $services);

        $compatibilities = [];
        foreach ($event->compatibilities as $compatibility) {
            if (\in_array($compatibility['name'], $serviceNames, true)) {
                // this app is a service
                $compatibility['statusName'] = ExtensionCompatibility::PLUGIN_COMPATIBILITY_UPDATABLE_FUTURE;
                $compatibility['statusMessage'] = 'With new Contena version';
                $compatibility['statusColor'] = 'yellow';
                $compatibility['statusVariant'] = null;
            }

            $compatibilities[] = $compatibility;
        }

        $event->compatibilities = $compatibilities;
    }
}
