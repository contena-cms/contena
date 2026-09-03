<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\Aggregate\PaymentAppTranslation\PaymentAppTranslationCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;

class PaymentAppEntity extends Entity
{
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $appCode;

    public protected(set) string $appSecret;

    public protected(set) ?string $name = null;

    public protected(set) bool $status;

    public protected(set) ?PaymentChannelConfigCollection $channelConfigs = null;

    public protected(set) ?PaymentAppChannelMethodCollection $channelMethods = null;

    public protected(set) ?PaymentOrderCollection $orders = null;

    public protected(set) ?PaymentAppTranslationCollection $translations = null;
}
