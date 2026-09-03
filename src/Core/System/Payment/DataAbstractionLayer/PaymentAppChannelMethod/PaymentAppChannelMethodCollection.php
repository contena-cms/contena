<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentAppChannelMethodEntity>
 */
class PaymentAppChannelMethodCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentAppChannelMethodEntity::class;
    }
}
