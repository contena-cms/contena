<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
final class PaymentRequest extends Struct
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $externalOrderNo,
        public readonly int $amount,
        public readonly string $method,
        public readonly string $subject,
        public readonly string $currencyCode = 'CNY',
        public readonly ?string $channel = null,
        public readonly ?string $deviceType = null,
        public readonly ?string $notifyUrl = null,
        public readonly ?string $returnUrl = null,
        public readonly array $extra = [],
        public readonly ?string $clientIp = null,
    ) {
    }
}
