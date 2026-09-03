<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentNotifyRecordEntity>
 */
class PaymentNotifyRecordCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentNotifyRecordEntity::class;
    }
}
