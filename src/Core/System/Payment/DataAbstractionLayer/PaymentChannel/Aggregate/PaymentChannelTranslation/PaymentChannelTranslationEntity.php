<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelEntity;

class PaymentChannelTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    public protected(set) string $paymentChannelId;

    public protected(set) ?string $name = null;

    public protected(set) ?PaymentChannelEntity $paymentChannel = null;
}
