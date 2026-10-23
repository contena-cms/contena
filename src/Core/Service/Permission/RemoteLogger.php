<?php declare(strict_types=1);

namespace Contena\Core\Service\Permission;


/**
 * @internal
 */
interface RemoteLogger
{
    public function log(PermissionsConsent $consent, ConsentState $state): void;
}
