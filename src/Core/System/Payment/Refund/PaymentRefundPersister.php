<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Refund;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persists new refund requests and reserves their amount atomically.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentRefundPersister
{
    /**
     * @param EntityRepository<PaymentRefundCollection> $paymentRefundRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentRefundRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array{orderId: string, externalRefundNo: string, refundAmount: int, channelCode: string, reason: ?string} $refundData
     */
    public function persist(array $refundData, Context $context): string
    {
        $refundId = Uuid::randomHex();
        $refundNo = $this->numberRangeValueGenerator->getValue(PaymentRefundDefinition::ENTITY_NAME, $context);

        $this->connection->transactional(function () use ($refundId, $refundNo, $refundData, $context): void {
            $this->paymentRefundRepository->create([[
                ...$refundData,
                'id' => $refundId,
                'refundNo' => $refundNo,
                'status' => PaymentRefundStatus::STATUS_PROCESSING,
            ]], $context);

            [$scope, $parameters] = $this->tenantScope($context);
            $reserved = $this->connection->executeStatement(
                'UPDATE `payment_order`
                 SET `refunded_amount` = `refunded_amount` + :refundAmount, `version` = `version` + 1
                 WHERE `id` = :orderId AND ' . $scope . ' AND `refunded_amount` + :refundAmount <= `amount`',
                [
                    ...$parameters,
                    'refundAmount' => $refundData['refundAmount'],
                    'orderId' => Uuid::fromHexToBytes($refundData['orderId']),
                ],
            );
            if ($reserved !== 1) {
                throw PaymentException::refundAmountExceeded($refundData['refundAmount']);
            }

            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent(
                new PaymentEntityReference(PaymentRefundDefinition::ENTITY_NAME, $refundId),
                $context,
            ));
        });

        return $refundId;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function tenantScope(Context $context): array
    {
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment writes require a platform or tenant context.');
        }
        if ($context->getTenantId() === null) {
            return ['`tenant_id` IS NULL', []];
        }

        return ['`tenant_id` = :tenantId', ['tenantId' => Uuid::fromHexToBytes($context->getTenantId())]];
    }
}
