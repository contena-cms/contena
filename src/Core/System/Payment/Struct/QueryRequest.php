<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

final class QueryRequest extends Struct
{
    public function __construct(
        public readonly string $orderNo,
        public readonly ?string $providerTradeNo = null,
        public readonly ?string $method = null,
    ) {
    }
}
