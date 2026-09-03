<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentChannelMethodEntity>
 */
class PaymentChannelMethodCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentChannelMethodEntity::class;
    }
}
