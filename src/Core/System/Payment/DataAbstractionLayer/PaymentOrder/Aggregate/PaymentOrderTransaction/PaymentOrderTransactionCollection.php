<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentOrderTransactionEntity>
 */
class PaymentOrderTransactionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentOrderTransactionEntity::class;
    }
}
