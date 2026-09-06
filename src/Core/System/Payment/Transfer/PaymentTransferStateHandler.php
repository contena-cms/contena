<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Transfer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\Payment\Event\PaymentStatusChangedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies gateway outcomes to transfer orders.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class PaymentTransferStateHandler
{
    /**
     * @param EntityRepository<PaymentTransferCollection> $paymentTransferRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentTransferRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function apply(PaymentTransferEntity $transfer, GatewayResult $result, Context $context): GatewayResult
    {
        return $this->connection->transactional(function () use ($transfer, $result, $context): GatewayResult {
            $current = $this->lock($transfer->getId(), $context);
            if (\in_array($current['status'], [PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_FAILED], true)) {
                return GatewayResult::fromArray(\is_array($current['response_data']) ? $current['response_data'] : null, $current['status']);
            }
            if ($current['status'] !== $transfer->state?->getTechnicalName()) {
                throw PaymentException::concurrentModification($transfer->transferNo);
            }

            $transfer->assign(['channelOrderId' => $current['channel_order_id']]);
            $this->paymentTransferRepository->update([[
                'id' => $transfer->getId(),
                'channelOrderId' => $result->response->resourceId ?? $transfer->channelOrderId,
                'channelStatus' => $result->status,
                'successTime' => $result->status === PaymentStatus::SUCCEEDED ? $this->clock->now() : null,
                'responseData' => $result->toArray(),
                'resultCode' => $result->response->code,
                'resultMessage' => $result->response->message,
            ]], $context);

            if ($result->status === PaymentStatus::SUCCEEDED) {
                $this->stateMachineRegistry->transition(new Transition(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId(), 'succeed', 'stateId'), $context);
            } elseif (\in_array($result->status, [PaymentStatus::FAILED, PaymentStatus::CLOSED], true)) {
                $this->stateMachineRegistry->transition(new Transition(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId(), 'fail', 'stateId'), $context);
            }

            $criteria = new Criteria([$transfer->getId()]);
            $criteria->addAssociation('state');
            $updated = $this->paymentTransferRepository->search($criteria, $context)->getEntities()->first()
                ?? throw PaymentException::transferNotFound($transfer->getId());
            if ($current['status'] !== $updated->state?->getTechnicalName()) {
                $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent(
                    new PaymentEntityReference(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId()),
                    $context,
                    $result,
                ));
            }

            return $result;
        });
    }

    public function recordFailure(PaymentTransferEntity $transfer, \Throwable $exception, Context $context): void
    {
        $this->connection->transactional(function () use ($transfer, $exception, $context): void {
            $current = $this->lock($transfer->getId(), $context);
            if (\in_array($current['status'], [PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_FAILED], true)) {
                return;
            }
            $this->paymentTransferRepository->update([[
                'id' => $transfer->getId(),
                'resultMessage' => mb_substr($exception->getMessage(), 0, 255),
            ]], $context);
        });
    }

    /**
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
        $row = $this->connection->fetchAssociative('SELECT (SELECT `technical_name` FROM `state_machine_state` WHERE `id` = `payment_transfer`.`state_id`) AS `status`, `response_data`, `channel_order_id` FROM `payment_transfer` WHERE `id` = :id AND ' . $scope . ' FOR UPDATE', $parameters);
        if ($row === false) {
            throw PaymentException::notificationResourceNotFound($id);
        }
        if (\is_string($row['response_data'])) {
            $row['response_data'] = json_decode($row['response_data'], true, 512, \JSON_THROW_ON_ERROR);
        }

        return $row;
    }
}
