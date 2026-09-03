<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentOrderEntity>
 */
class PaymentOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentOrderEntity::class;
    }
}
