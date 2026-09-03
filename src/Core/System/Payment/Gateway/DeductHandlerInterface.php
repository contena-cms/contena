<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;

interface DeductHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function deduct(PaymentRequest $request, array $config): PaymentResult;
}
