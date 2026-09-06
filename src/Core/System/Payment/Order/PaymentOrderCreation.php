<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Order;

/**
 * Identifiers returned after an order and its initial transaction are persisted.
 *
 * @internal
 */
final readonly class PaymentOrderCreation
{
    public function __construct(
        public string $orderId,
        public string $transactionId,
    ) {
    }
}
