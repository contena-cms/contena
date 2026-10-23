<?php declare(strict_types=1);

namespace Contena\Core\Service\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\Permission\PermissionsConsent;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class PermissionsGrantedEvent implements ContenaEvent
{
    public function __construct(
        public PermissionsConsent $permissionsConsent,
        public Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
