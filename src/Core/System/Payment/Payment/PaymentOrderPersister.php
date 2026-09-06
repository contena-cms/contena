<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentTransactionStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\Event\PaymentEntityCreatedEvent;
use Contena\Core\System\Payment\Event\PaymentResultAppliedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
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
class PaymentOrderPersister
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentOrderTransactionCollection> $paymentOrderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $paymentOrderTransactionRepository,
        private readonly PaymentOrderConverter $converter,
        private readonly PaymentOrderStateHandler $paymentOrderStateHandler,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly InitialStateIdLoader $initialStateIdLoader,
    ) {
    }

    public function persist(PaymentAppEntity $app, PaymentRequest $request, PaymentRoute $route, Context $context): string
    {
        $payload = $this->converter->convert($app, $request, $route, $context);

        return $this->connection->transactional(function () use ($payload, $context): string {
            $orderId = Uuid::randomHex();
            $this->paymentOrderRepository->create([[
                ...$payload,
                'id' => $orderId,
                'orderNo' => $this->numberRangeValueGenerator->getValue(PaymentOrderDefinition::ENTITY_NAME, $context),
                'stateId' => $this->initialStateIdLoader->get(PaymentOrderStates::STATE_MACHINE),
            ]], $context);
            $order = $this->getOrderById($orderId, $context);
            $this->eventDispatcher->dispatch(new PaymentEntityCreatedEvent($this->reference($order), $context));

            return $orderId;
        });
    }

    public function createPaymentTransaction(PaymentOrderEntity $order, Context $context): PaymentOrderTransactionEntity
    {
        return $this->createTransaction($order, 'create', $order->amount, $context);
    }

    public function createQueryTransaction(PaymentOrderEntity $order, Context $context): PaymentOrderTransactionEntity
    {
        return $this->createTransaction($order, 'query', 0, $context);
    }

    public function applyNotification(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $order = $this->getOrderById($target->entityId, $target->context);
        if ($order->channelCode !== $channel || $order->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($order->orderNo);
        }

        $this->connection->transactional(function () use ($order, $result, $target): void {
            $this->lock($order->getId(), $target->context);
            $this->applyOrderResult($this->getOrderById($order->getId(), $target->context), $result, $target->context);
        });
    }

    public function persistResult(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, PaymentResult $result, Context $context): PaymentResult
    {
        return $this->connection->transactional(function () use ($order, $transaction, $result, $context): PaymentResult {
            $this->lock($order->getId(), $context);
            $this->paymentOrderTransactionRepository->update([[
                'id' => $transaction->getId(),
                'channelRequestNo' => $result->providerRequestId,
                'channelTradeNo' => $result->providerResourceId,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $context);
            $this->paymentOrderStateHandler->applyTransactionStatus($transaction->getId(), $result->status, $context);

            return $this->applyOrderResult($this->getOrderById($order->getId(), $context), $result, $context);
        });
    }

    public function persistFailure(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, \Throwable $exception, Context $context): void
    {
        $this->connection->transactional(function () use ($order, $transaction, $exception, $context): void {
            $this->lock($order->getId(), $context);
            $this->paymentOrderTransactionRepository->update([[
                'id' => $transaction->getId(),
                'resultMessage' => mb_substr($exception->getMessage(), 0, 255),
            ]], $context);
            $this->applyOrderResult($this->getOrderById($order->getId(), $context), new PaymentResult(PaymentStatus::UNKNOWN), $context);
        });
    }

    public function getOrderById(string $orderId, Context $context): PaymentOrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('state');
        $order = $this->paymentOrderRepository->search($criteria, $context)->getEntities()->first();

        return $order ?? throw PaymentException::orderNotFound($orderId);
    }

    public function getTransactionById(string $transactionId, Context $context): PaymentOrderTransactionEntity
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('state');
        $transaction = $this->paymentOrderTransactionRepository->search($criteria, $context)->getEntities()->first();

        return $transaction ?? throw PaymentException::transactionNotFound($transactionId);
    }

    public function reference(PaymentOrderEntity $entity): PaymentEntityReference
    {
        return new PaymentEntityReference(PaymentOrderDefinition::ENTITY_NAME, $entity->getId());
    }

    public function find(string $appId, string $externalOrderNo, Context $context): ?PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addFilter(new EqualsFilter('externalOrderNo', $externalOrderNo));
        $criteria->setLimit(1);

        return $this->paymentOrderRepository->search($criteria, $context)->getEntities()->first();
    }

    private function createTransaction(PaymentOrderEntity $order, string $type, int $amount, Context $context): PaymentOrderTransactionEntity
    {
        return $this->connection->transactional(function () use ($order, $type, $amount, $context): PaymentOrderTransactionEntity {
            $this->lock($order->getId(), $context);
            $transactionId = Uuid::randomHex();
            $this->paymentOrderTransactionRepository->create([[
                'id' => $transactionId,
                'orderId' => $order->getId(),
                'transactionNo' => $this->numberRangeValueGenerator->getValue(PaymentOrderTransactionDefinition::ENTITY_NAME, $context),
                'type' => $type,
                'channelCode' => $order->channelCode,
                'methodCode' => $order->methodCode,
                'amount' => $amount,
                'stateId' => $this->initialStateIdLoader->get(PaymentTransactionStates::STATE_MACHINE),
            ]], $context);
            if ($type === 'create') {
                $this->paymentOrderRepository->update([['id' => $order->getId(), 'primaryTransactionId' => $transactionId]], $context);
            }

            return $this->getTransactionById($transactionId, $context);
        });
    }

    private function applyOrderResult(PaymentOrderEntity $order, PaymentResult $result, Context $context): PaymentResult
    {
        $current = $this->lock($order->getId(), $context);
        $previous = $current['status'];
        if (\in_array($previous, [PaymentOrderStates::STATE_SUCCEEDED, PaymentOrderStates::STATE_FAILED, PaymentOrderStates::STATE_CLOSED], true)) {
            return $result->status === $previous ? $result : new PaymentResult($previous, providerResourceId: \is_string($current['channel_trade_no']) ? $current['channel_trade_no'] : null);
        }
        if ($previous !== $order->state?->getTechnicalName()) {
            throw PaymentException::concurrentModification($order->orderNo);
        }

        $update = ['id' => $order->getId()];
        if ($result->providerResourceId !== null) {
            $update['channelTradeNo'] = $result->providerResourceId;
        }
        if ($result->status === PaymentStatus::SUCCEEDED) {
            $update['successTime'] = $this->clock->now();
        }
        $this->paymentOrderRepository->update([$update], $context);
        $this->paymentOrderStateHandler->applyOrderStatus($order, $result->status, $context);
        $updated = $this->getOrderById($order->getId(), $context);
        if ($previous !== $updated->state?->getTechnicalName()) {
            $this->eventDispatcher->dispatch(new PaymentResultAppliedEvent($this->reference($updated), $context, $result));
        }

        return $result;
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
        $row = $this->connection->fetchAssociative('SELECT (SELECT `technical_name` FROM `state_machine_state` WHERE `id` = `payment_order`.`state_id`) AS `status`, `channel_trade_no` FROM `payment_order` WHERE `id` = :id AND ' . $scope . ' FOR UPDATE', $parameters);
        if ($row === false) {
            throw PaymentException::notificationResourceNotFound($id);
        }

        return $row;
    }
}
