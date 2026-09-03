<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelTranslation\PaymentChannelTranslationCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodCollection;

class PaymentChannelEntity extends Entity
{
    use EntityIdTrait;

    public protected(set) string $code;

    public protected(set) ?string $name = null;

    /**
     * @var array<string, mixed>|null
     */
    public protected(set) ?array $configSchema = null;

    public protected(set) bool $status;

    public protected(set) int $sort;

    public protected(set) ?PaymentChannelMethodCollection $methods = null;

    public protected(set) ?PaymentChannelConfigCollection $configs = null;

    public protected(set) ?PaymentChannelTranslationCollection $translations = null;
}
