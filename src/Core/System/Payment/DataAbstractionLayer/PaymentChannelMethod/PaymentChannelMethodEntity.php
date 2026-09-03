<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\Aggregate\PaymentChannelMethodTranslation\PaymentChannelMethodTranslationCollection;

class PaymentChannelMethodEntity extends Entity
{
    use EntityIdTrait;

    public protected(set) string $channelId;

    public protected(set) string $methodCode;

    public protected(set) ?string $name = null;

    public protected(set) bool $status;

    public protected(set) int $sort;

    public protected(set) ?PaymentChannelEntity $channel = null;

    public protected(set) ?PaymentAppChannelMethodCollection $appChannelMethods = null;

    public protected(set) ?PaymentChannelMethodTranslationCollection $translations = null;
}
