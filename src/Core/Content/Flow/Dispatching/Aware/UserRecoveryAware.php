<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Aware;

use Contena\Core\Framework\Event\IsFlowEventAware;

#[IsFlowEventAware]
interface UserRecoveryAware
{
    public const CUSTOMER_RECOVERY_ID = 'userRecoveryId';

    public const CUSTOMER_RECOVERY = 'userRecovery';

    public function getUserRecoveryId(): string;
}
