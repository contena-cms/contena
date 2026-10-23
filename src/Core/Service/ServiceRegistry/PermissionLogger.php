<?php declare(strict_types=1);

namespace Contena\Core\Service\ServiceRegistry;

use Contena\Core\Service\Message\LogPermissionToRegistryMessage;
use Contena\Core\Service\Permission\ConsentState;
use Contena\Core\Service\Permission\PermissionsConsent;
use Contena\Core\Service\Permission\RemoteLogger;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class PermissionLogger implements RemoteLogger
{
    public const CONFIG_STORE_LICENSE_HOST = 'core.store.licenseHost';


    public function log(PermissionsConsent $consent, ConsentState $state): void
    {
        $this->messageBus->dispatch(new LogPermissionToRegistryMessage($consent, $state));
    }

    public function logSync(PermissionsConsent $consent, ConsentState $state): void
    {
        if ($state === ConsentState::GRANTED) {
            $this->client->saveConsent(
                new SaveConsentRequest(
                    identifier: $consent->identifier,
                    consentingUserId: $consent->consentingUserId,
                    shopIdentifier: $this->shopIdProvider->getShopId()->id,
                    consentDate: $consent->grantedAt->format(\DateTime::ATOM),
                    consentRevision: $consent->revision,
                    licenseHost: $this->systemConfigService->getString(self::CONFIG_STORE_LICENSE_HOST),
                )
            );
        }

        if ($state === ConsentState::REVOKED) {
            $this->client->revokeConsent($consent->identifier);
        }
    }
}
