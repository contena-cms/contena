<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\Aggregate\PaymentChannelMethodTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PaymentChannelMethodTranslationEntity>
 */
class PaymentChannelMethodTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentChannelMethodTranslationEntity::class;
    }
}
