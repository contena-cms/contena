<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentChannelConfigEntity>
 */
class PaymentChannelConfigCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentChannelConfigEntity::class;
    }
}
