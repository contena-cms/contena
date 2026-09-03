<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentAppEntity>
 */
class PaymentAppCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentAppEntity::class;
    }
}
