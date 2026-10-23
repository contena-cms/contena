<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;


#[IsFlowEventAware]
interface UserAware
{
    public const CUSTOMER_ID = 'userId';

    public const CUSTOMER = 'user';

    public function getUserId(): string;
}
