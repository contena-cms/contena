<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\Event\PaymentStatusChangedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\GatewayResponse;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies gateway outcomes to payment orders and their primary transaction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class PaymentOrderStateHandler
{
    private const array FINAL_STATES = [
        PaymentOrderStates::STATE_SUCCEEDED,
        PaymentOrderStates::STATE_FAILED,
        PaymentOrderStates::STATE_CLOSED,
    ];

    private const array ORDER_TRANSITIONS = [
        PaymentStatus::PENDING => [
            PaymentOrderStates::STATE_CREATED => 'submit',
            PaymentOrderStates::STATE_UNKNOWN => 'mark_pending',
        ],
        PaymentStatus::PROCESSING => [
            PaymentOrderStates::STATE_CREATED => 'process',
            PaymentOrderStates::STATE_PENDING => 'process',
            PaymentOrderStates::STATE_UNKNOWN => 'process',
        ],
        PaymentStatus::SUCCEEDED => [
            PaymentOrderStates::STATE_CREATED => 'succeed',
            PaymentOrderStates::STATE_PENDING => 'succeed',
            PaymentOrderStates::STATE_PROCESSING => 'succeed',
            PaymentOrderStates::STATE_UNKNOWN => 'succeed',
        ],
        PaymentStatus::FAILED => [
            PaymentOrderStates::STATE_CREATED => 'fail',
            PaymentOrderStates::STATE_PENDING => 'fail',
            PaymentOrderStates::STATE_PROCESSING => 'fail',
            PaymentOrderStates::STATE_UNKNOWN => 'fail',
        ],
        PaymentStatus::CLOSED => [
            PaymentOrderStates::STATE_CREATED => 'close',
            PaymentOrderStates::STATE_PENDING => 'close',
            PaymentOrderStates::STATE_PROCESSING => 'close',
            PaymentOrderStates::STATE_UNKNOWN => 'close',
        ],
        PaymentStatus::UNKNOWN => [
            PaymentOrderStates::STATE_CREATED => 'mark_unknown',
            PaymentOrderStates::STATE_PENDING => 'mark_unknown',
            PaymentOrderStates::STATE_PROCESSING => 'mark_unknown',
        ],
    ];

    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentOrderTransactionCollection> $paymentOrderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $paymentOrderTransactionRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function apply(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, GatewayResult $result, Context $context): GatewayResult
    {
        return $this->connection->transactional(function () use ($order, $transaction, $result, $context): GatewayResult {
            $current = $this->lock($order->getId(), $context);
            if (!\in_array($current['status'], self::FINAL_STATES, true)) {
                $this->paymentOrderTransactionRepository->update([[
                    'id' => $transaction->getId(),
                    'channelRequestNo' => $result->response->requestId,
                    'channelTradeNo' => $result->response->resourceId,
                    'responseData' => $result->toArray(),
                    'resultCode' => $result->response->code,
                    'resultMessage' => $result->response->message,
                ]], $context);
                $this->transitionTransaction($transaction->getId(), $result->status, $context);
            }

            return $this->applyOrder($order, $result, $current, $context);
        });
    }

    public function recordFailure(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, \Throwable $exception, Context $context): void
    {
        $this->connection->transactional(function () use ($order, $transaction, $exception, $context): void {
            $current = $this->lock($order->getId(), $context);
            if (\in_array($current['status'], self::FINAL_STATES, true)) {
                return;
            }
            $this->paymentOrderTransactionRepository->update([[
                'id' => $transaction->getId(),
                'resultMessage' => mb_substr($exception->getMessage(), 0, 255),
            ]], $context);
            $this->applyOrder($order, new GatewayResult(PaymentStatus::UNKNOWN), $current, $context);
        });
    }

    /**
     * @param array<string, mixed> $current
     */
    private function applyOrder(PaymentOrderEntity $order, GatewayResult $result, array $current, Context $context): GatewayResult
    {
        $previous = \is_string($current['status'] ?? null) ? $current['status'] : PaymentOrderStates::STATE_UNKNOWN;
        if (\in_array($previous, self::FINAL_STATES, true)) {
            return $result->status === $previous
                ? $result
                : new GatewayResult($previous, response: new GatewayResponse(resourceId: \is_string($current['channel_trade_no']) ? $current['channel_trade_no'] : null));
        }
        if ($previous !== $order->state?->getTechnicalName()) {
            throw PaymentException::concurrentModification($order->orderNo);
        }

        $update = ['id' => $order->getId()];
        if ($result->response->resourceId !== null) {
            $update['channelTradeNo'] = $result->response->resourceId;
        }
        if ($result->status === PaymentStatus::SUCCEEDED) {
            $update['successTime'] = $this->clock->now();
        }
        $this->paymentOrderRepository->update([$update], $context);

        $transition = self::ORDER_TRANSITIONS[$result->status][$previous] ?? null;
        if ($transition !== null) {
            $this->stateMachineRegistry->transition(new Transition(PaymentOrderDefinition::ENTITY_NAME, $order->getId(), $transition, 'stateId'), $context);
        }

        $criteria = new Criteria([$order->getId()]);
        $criteria->addAssociation('state');
        $updated = $this->paymentOrderRepository->search($criteria, $context)->getEntities()->first()
            ?? throw PaymentException::orderNotFound($order->getId());
        if ($previous !== $updated->state?->getTechnicalName()) {
            $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent(
                new PaymentEntityReference(PaymentOrderDefinition::ENTITY_NAME, $updated->getId()),
                $context,
                $result,
            ));
        }

        return $result;
    }

    private function transitionTransaction(string $transactionId, string $status, Context $context): void
    {
        $transition = match ($status) {
            PaymentStatus::SUCCEEDED => 'succeed',
            PaymentStatus::FAILED, PaymentStatus::CLOSED => 'fail',
            default => null,
        };
        if ($transition !== null) {
            $this->stateMachineRegistry->transition(new Transition(PaymentOrderTransactionDefinition::ENTITY_NAME, $transactionId, $transition, 'stateId'), $context);
        }
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
        $row = $this->connection->fetchAssociative('SELECT (SELECT `technical_name` FROM `state_machine_state` WHERE `id` = `payment_order`.`state_id`) AS `status`, `channel_trade_no` FROM `payment_order` WHERE `id` = :id AND ' . $scope . ' FOR UPDATE', $parameters);
        if ($row === false) {
            throw PaymentException::notificationResourceNotFound($id);
        }

        return $row;
    }
}
