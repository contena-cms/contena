<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentTransferEntity>
 */
class PaymentTransferCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentTransferEntity::class;
    }
}
