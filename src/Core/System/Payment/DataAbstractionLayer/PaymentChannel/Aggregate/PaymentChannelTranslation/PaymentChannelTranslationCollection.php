<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentChannelTranslationEntity>
 */
class PaymentChannelTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentChannelTranslationEntity::class;
    }
}
