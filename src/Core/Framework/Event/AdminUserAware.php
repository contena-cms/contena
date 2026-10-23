<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;


#[IsFlowEventAware]
interface AdminUserAware
{
    public const USER_RECOVERY = 'adminUserRecovery';

    public const USER_RECOVERY_ID = 'adminUserRecoveryId';

    public function getUserId(): string;
}
