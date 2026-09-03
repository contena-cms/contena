<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Struct\PaymentResult;

interface TransferHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function transfer(PaymentTransferEntity $transfer, array $config): PaymentResult;
}
