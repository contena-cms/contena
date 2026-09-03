<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

final class PaymentRequest extends Struct
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $externalOrderNo,
        public readonly int $amount,
        public readonly string $currencyCode,
        public readonly string $method,
        public readonly string $subject,
        public readonly ?string $channel = null,
        public readonly ?string $clientIp = null,
        public readonly ?string $notifyUrl = null,
        public readonly ?string $returnUrl = null,
        public readonly array $extra = [],
    ) {
    }
}
