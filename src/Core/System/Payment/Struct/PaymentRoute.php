<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Payment\Gateway\GatewayInterface;

final class PaymentRoute extends Struct
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly GatewayInterface $gateway,
        public readonly string $channelConfigId,
        public readonly array $config,
        public readonly bool $platformConfig,
    ) {
    }
}
