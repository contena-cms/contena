<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * Identifies the platform operation and exposes its normalized gateway outcome.
 *
 * @codeCoverageIgnore
 */
final class PaymentResult extends Struct
{
    public function __construct(
        public readonly string $number,
        public readonly string $externalNumber,
        public readonly GatewayResult $gatewayResult,
        public readonly ?string $transactionNumber = null,
    ) {
    }
}
