<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\Aggregate\PaymentChannelMethodTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodEntity;

class PaymentChannelMethodTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    public protected(set) string $paymentChannelMethodId;

    public protected(set) ?string $name = null;

    public protected(set) ?PaymentChannelMethodEntity $paymentChannelMethod = null;
}
