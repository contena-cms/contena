<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\TransferRequest;

interface TransferHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function transfer(TransferRequest $request, array $config): PaymentResult;
}
