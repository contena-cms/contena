<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

/**
 * Identifies the payment-domain entity involved in a lifecycle event.
 * Load it using the event Context; the reference never grants cross-tenant access.
 *
 * @codeCoverageIgnore
 */
final readonly class PaymentEntityReference
{
    public function __construct(
        public string $entityName,
        public string $entityId,
    ) {
    }
}
