<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentRefundEntity>
 */
class PaymentRefundCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentRefundEntity::class;
    }
}
