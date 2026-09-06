<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;

class PaymentChannelConfigEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) ?string $paymentAppId = null;

    public protected(set) string $channelId;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $config = null;

    public protected(set) bool $status;

    public protected(set) ?PaymentAppEntity $app = null;

    public protected(set) ?PaymentChannelEntity $channel = null;

    public protected(set) ?PaymentOrderCollection $orders = null;
}
