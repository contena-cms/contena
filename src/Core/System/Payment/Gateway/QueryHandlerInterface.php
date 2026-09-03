<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\QueryRequest;

interface QueryHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function query(QueryRequest $request, array $config): PaymentResult;
}
