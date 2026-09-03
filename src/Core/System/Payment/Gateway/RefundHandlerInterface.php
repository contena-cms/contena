<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\RefundRequest;

interface RefundHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function refund(RefundRequest $request, array $config): PaymentResult;
}
