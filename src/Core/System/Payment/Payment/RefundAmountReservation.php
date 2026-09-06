<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\PaymentException;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;

/**
 * Owns atomic refundable-balance mutations on the order aggregate.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class RefundAmountReservation
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function reserve(PaymentOrderEntity $order, int $amount, Context $context): int
    {
        [$tenantCondition, $parameters] = $this->tenantCondition($context);

        return (int) $this->connection->executeStatement(
            'UPDATE `payment_order`
             SET `refunded_amount` = `refunded_amount` + :refundAmount, `version` = `version` + 1
             WHERE `id` = :orderId AND ' . $tenantCondition . ' AND `refunded_amount` + :refundAmount <= `amount`',
            [
                ...$parameters,
                'refundAmount' => $amount,
                'orderId' => Uuid::fromHexToBytes($order->getId()),
            ],
        );
    }

    public function release(PaymentOrderEntity $order, int $amount, Context $context): void
    {
        [$tenantCondition, $parameters] = $this->tenantCondition($context);
        $this->connection->executeStatement(
            'UPDATE `payment_order`
             SET `refunded_amount` = `refunded_amount` - :refundAmount, `version` = `version` + 1
             WHERE `id` = :orderId AND ' . $tenantCondition . ' AND `refunded_amount` >= :refundAmount',
            [
                ...$parameters,
                'refundAmount' => $amount,
                'orderId' => Uuid::fromHexToBytes($order->getId()),
            ],
        );
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function tenantCondition(Context $context): array
    {
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Refund reservations require a platform or tenant context.');
        }
        if ($context->getTenantId() === null) {
            return ['`tenant_id` IS NULL', []];
        }

        return ['`tenant_id` = :tenantId', ['tenantId' => Uuid::fromHexToBytes($context->getTenantId())]];
    }
}
