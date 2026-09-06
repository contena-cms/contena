<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Refund;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\Event\PaymentResultAppliedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\Payment\RefundAmountReservation;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Refund\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
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
        private readonly RefundAmountReservation $refundAmountReservation,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function applyNotification(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $refund = $this->getRefundForNotification($target->entityId, $target->context);
        $order = $refund->order;
        if (!$order instanceof PaymentOrderEntity) {
            throw PaymentException::notificationResourceNotFound($refund->refundNo);
        }
        if ($refund->channelCode !== $channel || $order->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($refund->refundNo);
        }

        $this->persistResult($refund, $order, $result, $target->context);
    }

    public function persistResult(PaymentRefundEntity $refund, PaymentOrderEntity $order, PaymentResult $result, Context $context): PaymentResult
    {
        $status = match ($result->status) {
            PaymentStatus::SUCCEEDED => PaymentRefundStatus::STATUS_SUCCEEDED,
            PaymentStatus::FAILED, PaymentStatus::CLOSED => PaymentRefundStatus::STATUS_FAILED,
            default => PaymentRefundStatus::STATUS_PROCESSING,
        };
        $succeeded = $status === PaymentRefundStatus::STATUS_SUCCEEDED;

        return $this->connection->transactional(function () use ($refund, $order, $result, $status, $succeeded, $context): PaymentResult {
            $current = $this->lock($refund->getId(), $context);
            $refund = $this->getRefundById($refund->getId(), $context);
            $refund->assign(['status' => (int) $current['status'], 'channelRefundNo' => $current['channel_refund_no']]);
            if (\in_array($refund->status, [PaymentRefundStatus::STATUS_SUCCEEDED, PaymentRefundStatus::STATUS_FAILED], true)) {
                return PaymentResult::fromArray(\is_array($current['response_data']) ? $current['response_data'] : null, $refund->status === PaymentRefundStatus::STATUS_SUCCEEDED ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED);
            }
            $previous = $refund->status;
            $this->paymentRefundRepository->update([[
                'id' => $refund->getId(),
                'status' => $status,
                'channelRefundNo' => $result->providerResourceId ?? $refund->channelRefundNo,
                'successTime' => $succeeded ? $this->clock->now() : null,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $context);

            if ($status === PaymentRefundStatus::STATUS_FAILED) {
                $this->refundAmountReservation->release($order, $refund->refundAmount, $context);
            }
            if ($previous !== $status) {
                $this->eventDispatcher->dispatch(new PaymentResultAppliedEvent($this->reference($refund), $context, $result));
            }

            return $result;
        });
    }

    public function findRefund(string $orderId, string $externalRefundNo, Context $context): ?PaymentRefundEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('externalRefundNo', $externalRefundNo));
        $criteria->setLimit(1);
        $refund = $this->paymentRefundRepository->search($criteria, $context)->getEntities()->first();

        return $refund;
    }

    public function getRefundById(string $refundId, Context $context): PaymentRefundEntity
    {
        $refund = $this->paymentRefundRepository->search(new Criteria([$refundId]), $context)->getEntities()->first();

        return $refund ?? throw PaymentException::refundNotFound($refundId);
    }

    public function getRefundForNotification(string $refundId, Context $context): PaymentRefundEntity
    {
        $criteria = new Criteria([$refundId]);
        $criteria->addAssociation('order');
        $refund = $this->paymentRefundRepository->search($criteria, $context)->getEntities()->first();

        return $refund ?? throw PaymentException::notificationResourceNotFound($refundId);
    }

    public function reference(PaymentRefundEntity $entity): PaymentEntityReference
    {
        return new PaymentEntityReference(PaymentRefundDefinition::ENTITY_NAME, $entity->getId());
    }

    public function createRefund(PaymentOrderEntity $order, RefundRequest $request, Context $context): PaymentRefundEntity
    {
        $refundId = Uuid::randomHex();
        $refundNo = $this->numberRangeValueGenerator->getValue(PaymentRefundDefinition::ENTITY_NAME, $context);
        $this->connection->transactional(function () use ($refundId, $refundNo, $order, $request, $context): void {
            $this->paymentRefundRepository->create([[
                'id' => $refundId,
                'refundNo' => $refundNo,
                'orderId' => $order->getId(),
                'externalRefundNo' => $request->externalRefundNo,
                'refundAmount' => $request->amount,
                'channelCode' => $order->channelCode,
                'status' => PaymentRefundStatus::STATUS_PROCESSING,
                'reason' => $request->reason,
            ]], $context);

            if ($this->refundAmountReservation->reserve($order, $request->amount, $context) !== 1) {
                throw PaymentException::refundAmountExceeded($request->amount);
            }
            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent($this->reference($this->getRefundById($refundId, $context)), $context));
        });

        return $this->getRefundById($refundId, $context);
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
     * The locking read, unlike a DAL snapshot read, sees the latest committed row.
     *
     * @return array<string, mixed>
     */
    private function lock(string $id, Context $context): array
    {
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment writes require a platform or tenant context.');
        }
        $parameters = ['id' => Uuid::fromHexToBytes($id)];
        $scope = '`tenant_id` IS NULL';
        if ($context->getTenantId() !== null) {
            $scope = '`tenant_id` = :tenantId';
            $parameters['tenantId'] = Uuid::fromHexToBytes($context->getTenantId());
        }
        $row = $this->connection->fetchAssociative('SELECT `status`, `response_data`, `channel_refund_no` FROM `payment_refund` WHERE `id` = :id AND ' . $scope . ' FOR UPDATE', $parameters);
        if ($row === false) {
            throw PaymentException::notificationResourceNotFound($id);
        }

        if (\is_string($row['response_data'])) {
            $row['response_data'] = json_decode($row['response_data'], true, 512, \JSON_THROW_ON_ERROR);
        }

        return $row;
    }
}
