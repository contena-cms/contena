<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Aware;

use Contena\Core\Framework\Event\IsFlowEventAware;
use Contena\Core\Framework\Log\Package;

#[Package('after-sales')]
#[IsFlowEventAware]
interface CustomerRecoveryAware
{
    public const CUSTOMER_RECOVERY_ID = 'customerRecoveryId';

    public const CUSTOMER_RECOVERY = 'customerRecovery';

    public function getCustomerRecoveryId(): string;
}
