<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Transfer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\Event\PaymentResultAppliedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Transfer\Struct\TransferRequest;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
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
class PaymentTransferPersister
{
    /**
     * @param EntityRepository<PaymentTransferCollection> $paymentTransferRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentTransferRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function applyNotification(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $transfer = $this->getTransferById($target->entityId, $target->context);
        if ($transfer->channelCode !== $channel || $transfer->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($transfer->transferNo);
        }

        $this->persistResult($transfer, $result, $target->context);
    }

    public function persistResult(PaymentTransferEntity $transfer, PaymentResult $result, Context $context): PaymentResult
    {
        return $this->connection->transactional(function () use ($transfer, $result, $context): PaymentResult {
            $current = $this->lock($transfer->getId(), $context);
            $transfer = $this->getTransferById($transfer->getId(), $context);
            if (\in_array($current['status'], [PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_FAILED], true)) {
                return PaymentResult::fromArray(\is_array($current['response_data']) ? $current['response_data'] : null, $current['status']);
            }
            if ($current['status'] !== $transfer->state?->getTechnicalName()) {
                throw PaymentException::concurrentModification($transfer->transferNo);
            }
            $transfer->assign(['channelOrderId' => $current['channel_order_id']]);
            $previous = $current['status'];
            $this->paymentTransferRepository->update([[
                'id' => $transfer->getId(),
                'channelOrderId' => $result->providerResourceId ?? $transfer->channelOrderId,
                'channelStatus' => $result->status,
                'successTime' => $result->status === PaymentStatus::SUCCEEDED ? $this->clock->now() : null,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $context);

            if ($result->status === PaymentStatus::SUCCEEDED) {
                $this->stateMachineRegistry->transition(new Transition(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId(), 'succeed', 'stateId'), $context);
            } elseif (\in_array($result->status, [PaymentStatus::FAILED, PaymentStatus::CLOSED], true)) {
                $this->stateMachineRegistry->transition(new Transition(PaymentTransferDefinition::ENTITY_NAME, $transfer->getId(), 'fail', 'stateId'), $context);
            }
            $updated = $this->getTransferById($transfer->getId(), $context);
            if ($previous !== $updated->state?->getTechnicalName()) {
                $this->eventDispatcher->dispatch(new PaymentResultAppliedEvent($this->reference($updated), $context, $result));
            }

            return $result;
        });
    }

    public function findTransfer(string $appId, string $externalTransferNo, Context $context): ?PaymentTransferEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addFilter(new EqualsFilter('externalTransferNo', $externalTransferNo));
        $criteria->addAssociation('state');
        $criteria->setLimit(1);
        $transfer = $this->paymentTransferRepository->search($criteria, $context)->getEntities()->first();

        return $transfer;
    }

    public function getTransferById(string $transferId, Context $context): PaymentTransferEntity
    {
        $criteria = new Criteria([$transferId]);
        $criteria->addAssociation('state');
        $transfer = $this->paymentTransferRepository->search($criteria, $context)->getEntities()->first();

        return $transfer ?? throw PaymentException::transferNotFound($transferId);
    }

    public function createTransfer(PaymentAppEntity $app, TransferRequest $request, PaymentRoute $route, Context $context): string
    {
        return $this->connection->transactional(function () use ($app, $request, $route, $context): string {
            $transferId = Uuid::randomHex();

            $this->paymentTransferRepository->create([[
                'id' => $transferId,
                'paymentAppId' => $app->getId(),
                'transferNo' => $this->numberRangeValueGenerator->getValue(PaymentTransferDefinition::ENTITY_NAME, $context),
                'externalTransferNo' => $request->externalTransferNo,
                'amount' => $request->amount,
                'currencyCode' => strtoupper($request->currencyCode),
                'channelCode' => $route->gateway->code(),
                'channelConfigId' => $route->channelConfigId,
                'stateId' => $this->initialStateId($context),
                'payee' => $request->payee,
                'payeeName' => $request->payeeName,
                'remark' => $request->remark,
                'notifyUrl' => $request->notifyUrl,
                'channelExtra' => $request->extra,
            ]], $context);

            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent($this->reference($this->getTransferById($transferId, $context)), $context));

            return $transferId;
        });
    }

    public function reference(PaymentTransferEntity $entity): PaymentEntityReference
    {
        return new PaymentEntityReference(PaymentTransferDefinition::ENTITY_NAME, $entity->getId());
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

    private function initialStateId(Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine(PaymentTransferStates::STATE_MACHINE, $context)->getInitialStateId()
            ?? throw PaymentException::invalidRequest('Payment transfer state machine has no initial state.');
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
