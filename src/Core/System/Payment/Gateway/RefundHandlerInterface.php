<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\Struct\PaymentResult;

interface RefundHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function refund(PaymentRefundEntity $refund, PaymentOrderEntity $order, array $config): PaymentResult;
}
