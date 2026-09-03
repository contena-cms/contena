<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

final class RefundRequest extends Struct
{
    public function __construct(
        public readonly string $externalRefundNo,
        public readonly int $amount,
        public readonly ?string $orderNo = null,
        public readonly ?string $externalOrderNo = null,
        public readonly ?string $reason = null,
    ) {
    }
}
