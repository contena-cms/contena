<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Log\Package;

#[IsFlowEventAware]
interface AdminUserAware
{
    public const USER_RECOVERY = 'admin_userRecovery';

    public const USER_RECOVERY_ID = 'admin_userRecoveryId';

    public function getUserId(): string;
}
