<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod;

use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodEntity;

class PaymentAppChannelMethodEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $paymentAppId;

    public protected(set) string $channelMethodId;

    /**
     * @var array<mixed>|null
     */
    public protected(set) ?array $config = null;

    public protected(set) int $sort;

    public protected(set) bool $status;

    public protected(set) ?string $ruleId = null;

    public protected(set) ?PaymentAppEntity $app = null;

    public protected(set) ?PaymentChannelMethodEntity $channelMethod = null;

    public protected(set) ?RuleEntity $rule = null;
}
