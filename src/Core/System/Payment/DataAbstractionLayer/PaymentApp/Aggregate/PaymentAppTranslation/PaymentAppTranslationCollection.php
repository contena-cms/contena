<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\Aggregate\PaymentAppTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentAppTranslationEntity>
 */
class PaymentAppTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentAppTranslationEntity::class;
    }
}
