<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

final class RefundRequest extends Struct
{
    public function __construct(
        public readonly string $orderNo,
        public readonly string $refundNo,
        public readonly int $amount,
        public readonly int $totalAmount,
        public readonly string $currencyCode,
        public readonly ?string $providerTradeNo = null,
        public readonly ?string $reason = null,
    ) {
    }
}
