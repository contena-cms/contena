<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentRecurringEntity>
 */
class PaymentRecurringCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentRecurringEntity::class;
    }
}
