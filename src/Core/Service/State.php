<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
enum State: string
{
    case ACTIVE = 'active';

    case PENDING_PERMISSIONS = 'pending_permissions';

    case INACTIVE = 'inactive';

    public static function state(AppEntity $appEntity): State
    {
        if ($appEntity->getRequestedPrivileges() === [] && $appEntity->isActive()) {
            return State::ACTIVE;
        }

        if ($appEntity->getRequestedPrivileges() !== [] && $appEntity->isActive()) {
            return State::PENDING_PERMISSIONS;
        }

        return State::INACTIVE;
    }
}
