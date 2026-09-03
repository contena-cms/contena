<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\PaymentResult;

interface CustomOperationHandlerInterface extends GatewayInterface
{
    public function supports(string $operation): bool;

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $config
     */
    public function execute(string $operation, array $request, array $config): PaymentResult;
}
