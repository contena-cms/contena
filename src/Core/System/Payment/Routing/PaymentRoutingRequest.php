<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Payment\Gateway\GatewayInterface;

/**
 * Routing facts shared by all payment domains, independently of their inputs.
 *
 * @codeCoverageIgnore
 */
final class PaymentRoutingRequest extends Struct
{
    /**
     * @param class-string<GatewayInterface> $capability
     */
    public function __construct(
        public readonly string $operation,
        public readonly string $capability,
        public readonly ?string $methodCode = null,
        public readonly ?string $preferredChannel = null,
        public readonly ?int $amount = null,
        public readonly ?string $currencyCode = null,
    ) {
    }
}
