<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;

/**
 * @internal
 */
class SystemHeartbeatEvent implements Hookable
{
    final public const NAME = 'app.system_heartbeat';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return array{}
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
