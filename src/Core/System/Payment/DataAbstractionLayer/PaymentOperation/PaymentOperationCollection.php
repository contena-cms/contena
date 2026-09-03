<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOperation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentOperationEntity>
 */
class PaymentOperationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentOperationEntity::class;
    }
}
