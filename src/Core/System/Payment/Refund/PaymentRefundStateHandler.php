<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Refund;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\Event\PaymentStatusChangedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies gateway outcomes to refunds and releases failed reservations.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class PaymentRefundStateHandler
{
    /**
     * @param EntityRepository<PaymentRefundCollection> $paymentRefundRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentRefundRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function apply(PaymentRefundEntity $refund, PaymentOrderEntity $order, GatewayResult $result, Context $context): GatewayResult
    {
        $status = match ($result->status) {
            PaymentStatus::SUCCEEDED => PaymentRefundStatus::STATUS_SUCCEEDED,
            PaymentStatus::FAILED, PaymentStatus::CLOSED => PaymentRefundStatus::STATUS_FAILED,
            default => PaymentRefundStatus::STATUS_PROCESSING,
        };

        return $this->connection->transactional(function () use ($refund, $order, $result, $status, $context): GatewayResult {
            $current = $this->lock($refund->getId(), $context);
            $refund->assign(['status' => (int) $current['status'], 'channelRefundNo' => $current['channel_refund_no']]);
            if (\in_array($refund->status, [PaymentRefundStatus::STATUS_SUCCEEDED, PaymentRefundStatus::STATUS_FAILED], true)) {
                return GatewayResult::fromArray(
                    \is_array($current['response_data']) ? $current['response_data'] : null,
                    $refund->status === PaymentRefundStatus::STATUS_SUCCEEDED ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED,
                );
            }

            $this->paymentRefundRepository->update([[
                'id' => $refund->getId(),
                'status' => $status,
                'channelRefundNo' => $result->response->resourceId ?? $refund->channelRefundNo,
                'successTime' => $status === PaymentRefundStatus::STATUS_SUCCEEDED ? $this->clock->now() : null,
                'responseData' => $result->toArray(),
                'resultCode' => $result->response->code,
                'resultMessage' => $result->response->message,
            ]], $context);

            if ($status === PaymentRefundStatus::STATUS_FAILED) {
                [$scope, $parameters] = $this->tenantScope($context);
                $this->connection->executeStatement(
                    'UPDATE `payment_order`
                     SET `refunded_amount` = `refunded_amount` - :refundAmount, `version` = `version` + 1
                     WHERE `id` = :orderId AND ' . $scope . ' AND `refunded_amount` >= :refundAmount',
                    [
                        ...$parameters,
                        'refundAmount' => $refund->refundAmount,
                        'orderId' => Uuid::fromHexToBytes($order->getId()),
                    ],
                );
            }
            if ($refund->status !== $status) {
                $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent(
                    new PaymentEntityReference(PaymentRefundDefinition::ENTITY_NAME, $refund->getId()),
                    $context,
                    $result,
                ));
            }

            return $result;
        });
    }

    public function recordFailure(PaymentRefundEntity $refund, \Throwable $exception, Context $context): void
    {
        $this->connection->transactional(function () use ($refund, $exception, $context): void {
            $current = $this->lock($refund->getId(), $context);
            if (\in_array((int) $current['status'], [PaymentRefundStatus::STATUS_SUCCEEDED, PaymentRefundStatus::STATUS_FAILED], true)) {
                return;
            }
            $this->paymentRefundRepository->update([[
                'id' => $refund->getId(),
                'resultMessage' => mb_substr($exception->getMessage(), 0, 255),
            ]], $context);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function lock(string $id, Context $context): array
    {
        [$scope, $parameters] = $this->tenantScope($context);
        $parameters['id'] = Uuid::fromHexToBytes($id);
        $row = $this->connection->fetchAssociative('SELECT `status`, `response_data`, `channel_refund_no` FROM `payment_refund` WHERE `id` = :id AND ' . $scope . ' FOR UPDATE', $parameters);
        if ($row === false) {
            throw PaymentException::notificationResourceNotFound($id);
        }
        if (\is_string($row['response_data'])) {
            $row['response_data'] = json_decode($row['response_data'], true, 512, \JSON_THROW_ON_ERROR);
        }

        return $row;
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
