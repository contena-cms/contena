<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Struct\GatewayResult;

interface PaymentQueryHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function query(PaymentOrderEntity $order, array $config): GatewayResult;
}
