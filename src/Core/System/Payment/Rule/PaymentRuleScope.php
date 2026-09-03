<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Rule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;

/**
 * @codeCoverageIgnore
 */
final class PaymentRuleScope extends RuleScope
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        public readonly PaymentAppEntity $app,
        public readonly string $operation,
        public readonly ?string $method = null,
        public readonly ?string $preferredChannel = null,
        public readonly ?int $amount = null,
        public readonly ?string $currencyCode = null,
        public readonly array $data = [],
    ) {
        parent::__construct($context);
    }
}
