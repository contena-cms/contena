<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
#[IsFlowEventAware]
interface CustomerGroupAware
{
    public const CUSTOMER_GROUP_ID = 'customerGroupId';

    public const CUSTOMER_GROUP = 'customerGroup';

    public function getCustomerGroupId(): string;
}
