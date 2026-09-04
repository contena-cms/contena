<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;

/**
 * Inputs used to select a payment route.
 *
 * Routing may use rules, explicit preferences, or another selector. The
 * request therefore stays independent from the rule engine's RuleScope.
 *
 * @internal
 */
final class PaymentRouteRequest extends Struct
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly Context $context,
        public readonly PaymentAppEntity $app,
        public readonly string $operation,
        public readonly ?string $method = null,
        public readonly ?string $preferredChannel = null,
        public readonly ?int $amount = null,
        public readonly ?string $currencyCode = null,
        public readonly array $data = [],
    ) {
    }
}
