<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Authorization\Policy\Policy;
use Contena\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\Webhook;
use Contena\Core\Service\Event\CommercialLicenseProvidedEvent;

/**
 * Restricts the events that carry service data to self-managed apps.
 *
 * @internal
 */
#[Package('framework')]
final class ServiceWebhookPolicy implements Policy
{
    public function __construct(private readonly ActiveAppsLoader $activeAppsLoader)
    {
    }

    public function handles(string $eventName): bool
    {
        return $eventName === CommercialLicenseProvidedEvent::NAME;
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        return $subscriber->manifest?->getMetadata()->isSelfManaged() ?? false;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        if ($webhook->appName === null) {
            return false;
        }

        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            if ($app['name'] === $webhook->appName) {
                return $app['selfManaged'];
            }
        }

        return false;
    }
}
