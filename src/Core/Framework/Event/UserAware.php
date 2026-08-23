<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

#[IsFlowEventAware]
interface UserAware
{
    public const string USER_ID = 'userId';
    public const string USER_RECOVERY = 'userRecovery';
    public const string USER_RECOVERY_ID = 'userRecoveryId';

    public function getUserId(): string;
}
