<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;


#[IsFlowEventAware]
interface UserGroupAware
{
    public const CUSTOMER_GROUP_ID = 'userGroupId';

    public const CUSTOMER_GROUP = 'userGroup';

    public function getUserGroupId(): string;
}
