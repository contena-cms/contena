<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentChannelNotifyRecordEntity>
 */
class PaymentChannelNotifyRecordCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentChannelNotifyRecordEntity::class;
    }
}
