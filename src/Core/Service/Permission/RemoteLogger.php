<?php declare(strict_types=1);

namespace Contena\Core\Service\Permission;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface RemoteLogger
{
    public function log(PermissionsConsent $consent, ConsentState $state): void;
}
