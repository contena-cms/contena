<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Struct\PaymentResult;

interface PaymentHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function pay(PaymentOrderEntity $order, array $config): PaymentResult;
}
