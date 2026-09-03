<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

final class TransferRequest extends Struct
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $externalTransferNo,
        public readonly int $amount,
        public readonly string $currencyCode,
        public readonly string $payee,
        public readonly string $payeeName,
        public readonly ?string $channel = null,
        public readonly ?string $remark = null,
        public readonly ?string $notifyUrl = null,
        public readonly array $extra = [],
    ) {
    }
}
