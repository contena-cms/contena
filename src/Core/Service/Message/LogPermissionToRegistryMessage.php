<?php declare(strict_types=1);

namespace Contena\Core\Service\Message;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;
use Contena\Core\Service\Permission\ConsentState;
use Contena\Core\Service\Permission\PermissionsConsent;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class LogPermissionToRegistryMessage implements AsyncMessageInterface
{
    public function __construct(public readonly PermissionsConsent $permissionsConsent, public readonly ConsentState $consentState)
    {
    }
}
