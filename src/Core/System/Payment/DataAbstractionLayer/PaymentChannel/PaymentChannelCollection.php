<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentChannelEntity>
 */
class PaymentChannelCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentChannelEntity::class;
    }
}
