<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;


#[IsFlowEventAware]
interface OrderAware
{
    public const ORDER = 'order';

    public const ORDER_ID = 'orderId';

    public function getOrderId(): string;
}
