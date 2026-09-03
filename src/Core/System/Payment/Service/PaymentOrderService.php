<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

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
use Contena\Core\System\Payment\Event\PaymentGatewayCallCompletedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayCallFailedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayCallStartedEvent;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Rule\PaymentRuleScope;
use Contena\Core\System\Payment\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class PaymentOrderService
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentOrderTransactionCollection> $paymentOrderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $paymentOrderTransactionRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult
    {
        $this->validatePaymentRequest($request);

        $existing = $this->findByExternalOrderNo($app->getId(), $request->externalOrderNo, $context);
        if ($existing instanceof PaymentOrderEntity) {
            $this->assertSamePayment($existing, $request);

            return $this->resultFromOrder($existing);
        }

        $route = $this->routeResolver->resolve(new PaymentRuleScope(
            $context,
            $app,
            PaymentOperation::PAY,
            $request->method,
            $request->channel,
            $request->amount,
            strtoupper($request->currencyCode),
            $request->extra,
        ));
        if (!$route->gateway instanceof PaymentHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::PAY);
        }

        $orderId = Uuid::randomHex();
        $orderNo = $this->numberRangeValueGenerator->getValue(PaymentOrderDefinition::ENTITY_NAME, $context);
        $transactionId = Uuid::randomHex();
        $transactionNo = $this->numberRangeValueGenerator->getValue(PaymentOrderTransactionDefinition::ENTITY_NAME, $context);
        $this->paymentOrderRepository->create([[
            'id' => $orderId,
            'paymentAppId' => $app->getId(),
            'orderNo' => $orderNo,
            'externalOrderNo' => $request->externalOrderNo,
            'amount' => $request->amount,
            'currencyCode' => strtoupper($request->currencyCode),
            'channelCode' => $route->gateway->code(),
            'methodCode' => $request->method,
            'deviceType' => $request->deviceType ?? $request->method,
            'subject' => $request->subject,
            'clientIp' => $request->clientIp,
            'channelExtra' => $request->extra,
            'notifyUrl' => $request->notifyUrl,
            'returnUrl' => $request->returnUrl,
            'channelConfigId' => $route->channelConfigId,
            'stateId' => $this->initialStateId(PaymentOrderStates::STATE_MACHINE, $context),
            'transactions' => [[
                'id' => $transactionId,
                'transactionNo' => $transactionNo,
                'type' => 'create',
                'channelCode' => $route->gateway->code(),
                'methodCode' => $request->method,
                'amount' => $request->amount,
                'stateId' => $this->initialStateId(PaymentTransactionStates::STATE_MACHINE, $context),
                'requestData' => [
                    'orderNo' => $orderNo,
                    'amount' => $request->amount,
                    'currencyCode' => strtoupper($request->currencyCode),
                    'method' => $request->method,
                    'subject' => $request->subject,
                    'extra' => $request->extra,
                ],
            ]],
        ]], $context);

        $order = $this->loadOrder($orderId, $context);
        $transaction = $this->loadTransaction($transactionId, $context);

        return $this->executePayment($order, $transaction, $route, $context);
    }

    public function query(PaymentAppEntity $app, QueryRequest $request, Context $context): PaymentResult
    {
        $order = $this->findOrder($app->getId(), $request, $context);
        $route = $this->routeResolver->resolveConfigured($order->channelCode, $order->channelConfigId, PaymentOperation::QUERY, $context);
        if (!$route->gateway instanceof QueryHandlerInterface) {
            throw PaymentException::capabilityNotSupported($order->channelCode, PaymentOperation::QUERY);
        }

        $transactionId = Uuid::randomHex();
        $transactionNo = $this->numberRangeValueGenerator->getValue(PaymentOrderTransactionDefinition::ENTITY_NAME, $context);
        $this->paymentOrderTransactionRepository->create([[
            'id' => $transactionId,
            'orderId' => $order->getId(),
            'transactionNo' => $transactionNo,
            'type' => 'query',
            'channelCode' => $order->channelCode,
            'methodCode' => $order->methodCode,
            'amount' => 0,
            'stateId' => $this->initialStateId(PaymentTransactionStates::STATE_MACHINE, $context),
            'requestData' => ['orderNo' => $order->orderNo, 'channelTradeNo' => $order->channelTradeNo],
        ]], $context);

        return $this->executeQuery($order, $this->loadTransaction($transactionId, $context), $route, $context);
    }

    public function findOrder(string $paymentAppId, QueryRequest $request, Context $context): PaymentOrderEntity
    {
        $field = $request->orderNo !== null ? 'orderNo' : 'externalOrderNo';
        $reference = $request->orderNo ?? $request->externalOrderNo;
        if ($reference === null || $reference === '') {
            throw PaymentException::invalidRequest('An order number or external order number is required.');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $paymentAppId));
        $criteria->addFilter(new EqualsFilter($field, $reference));
        $this->addOrderAssociations($criteria);
        $criteria->setLimit(1);

        $order = $this->paymentOrderRepository->search($criteria, $context)->getEntities()->first();
        if (!$order instanceof PaymentOrderEntity) {
            throw PaymentException::orderNotFound($reference);
        }

        return $order;
    }

    public function applyNotification(string $channel, string $channelConfigId, PaymentNotificationTarget $target, PaymentResult $result): void
    {
        $order = $this->loadOrder($target->entityId, $target->context);
        if ($order->channelCode !== $channel || $order->channelConfigId !== $channelConfigId) {
            throw PaymentException::notificationConfigurationMismatch($order->orderNo);
        }

        $this->connection->transactional(function () use ($order, $result, $target): void {
            $update = ['id' => $order->getId()];
            if ($result->providerResourceId !== null) {
                $update['channelTradeNo'] = $result->providerResourceId;
            }
            if ($result->status === PaymentStatus::SUCCEEDED && $order->state?->getTechnicalName() !== PaymentOrderStates::STATE_SUCCEEDED) {
                $update['successTime'] = $this->clock->now();
            }
            $this->paymentOrderRepository->update([$update], $target->context);
            $this->transitionOrder($order, $result->status, $target->context);
        });
    }

    private function executePayment(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, PaymentRoute $route, Context $context): PaymentResult
    {
        $this->dispatchStarted($order, PaymentOperation::PAY, $context);

        try {
            \assert($route->gateway instanceof PaymentHandlerInterface);
            $result = $route->gateway->pay($order, $route->config);
        } catch (\Throwable $exception) {
            $this->persistFailure($order, $transaction, $exception, $context, true);
            $this->dispatchFailed($order, PaymentOperation::PAY, $exception, $context);

            throw $exception;
        }

        $this->persistResult($order, $transaction, $result, $context, true);
        $this->dispatchCompleted($order, PaymentOperation::PAY, $result, $context);

        return $result->withResource($order->orderNo, $order->externalOrderNo, $transaction->transactionNo);
    }

    private function executeQuery(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, PaymentRoute $route, Context $context): PaymentResult
    {
        $this->dispatchStarted($order, PaymentOperation::QUERY, $context);

        try {
            \assert($route->gateway instanceof QueryHandlerInterface);
            $result = $route->gateway->query($order, $route->config);
        } catch (\Throwable $exception) {
            $this->persistFailure($order, $transaction, $exception, $context, false);
            $this->dispatchFailed($order, PaymentOperation::QUERY, $exception, $context);

            throw $exception;
        }

        $this->persistResult($order, $transaction, $result, $context, false);
        $this->dispatchCompleted($order, PaymentOperation::QUERY, $result, $context);

        return $result->withResource($order->orderNo, $order->externalOrderNo, $transaction->transactionNo);
    }

    private function persistResult(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, PaymentResult $result, Context $context, bool $primary): void
    {
        $this->connection->transactional(function () use ($order, $transaction, $result, $context, $primary): void {
            $this->paymentOrderTransactionRepository->update([[
                'id' => $transaction->getId(),
                'channelRequestNo' => $result->providerRequestId,
                'channelTradeNo' => $result->providerResourceId,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $context);
            $this->transitionTransaction($transaction->getId(), $result->status === PaymentStatus::FAILED ? 'fail' : 'succeed', $context);

            $orderUpdate = ['id' => $order->getId()];
            if ($primary) {
                $orderUpdate['primaryTransactionId'] = $transaction->getId();
            }
            if ($result->providerResourceId !== null) {
                $orderUpdate['channelTradeNo'] = $result->providerResourceId;
            }
            if ($result->status === PaymentStatus::SUCCEEDED) {
                $orderUpdate['successTime'] = $this->clock->now();
            }
            $this->paymentOrderRepository->update([$orderUpdate], $context);
            $this->transitionOrder($order, $result->status, $context);
        });
    }

    private function persistFailure(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, \Throwable $exception, Context $context, bool $primary): void
    {
        $this->connection->transactional(function () use ($order, $transaction, $exception, $context, $primary): void {
            $this->paymentOrderTransactionRepository->update([[
                'id' => $transaction->getId(),
                'resultMessage' => $exception->getMessage(),
            ]], $context);
            $this->transitionTransaction($transaction->getId(), 'fail', $context);
            if ($primary) {
                $this->paymentOrderRepository->update([[
                    'id' => $order->getId(),
                    'primaryTransactionId' => $transaction->getId(),
                ]], $context);
            }
            $this->transitionOrder($order, PaymentStatus::UNKNOWN, $context);
        });
    }

    private function transitionOrder(PaymentOrderEntity $order, string $status, Context $context): void
    {
        $current = $order->state?->getTechnicalName();
        $transition = match ($status) {
            PaymentStatus::PENDING => $current === PaymentOrderStates::STATE_CREATED ? 'submit' : ($current === PaymentOrderStates::STATE_UNKNOWN ? 'mark_pending' : null),
            PaymentStatus::PROCESSING => \in_array($current, [PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_PENDING, PaymentOrderStates::STATE_UNKNOWN], true) ? 'process' : null,
            PaymentStatus::SUCCEEDED => \in_array($current, [PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_PENDING, PaymentOrderStates::STATE_PROCESSING, PaymentOrderStates::STATE_UNKNOWN], true) ? 'succeed' : null,
            PaymentStatus::FAILED => \in_array($current, [PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_PENDING, PaymentOrderStates::STATE_PROCESSING, PaymentOrderStates::STATE_UNKNOWN], true) ? 'fail' : null,
            PaymentStatus::CLOSED => \in_array($current, [PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_PENDING, PaymentOrderStates::STATE_PROCESSING, PaymentOrderStates::STATE_UNKNOWN], true) ? 'close' : null,
            PaymentStatus::UNKNOWN => \in_array($current, [PaymentOrderStates::STATE_CREATED, PaymentOrderStates::STATE_PENDING, PaymentOrderStates::STATE_PROCESSING], true) ? 'mark_unknown' : null,
            default => null,
        };

        if ($transition !== null) {
            $this->stateMachineRegistry->transition(new Transition(PaymentOrderDefinition::ENTITY_NAME, $order->getId(), $transition, 'stateId'), $context);
        }
    }

    private function transitionTransaction(string $transactionId, string $transition, Context $context): void
    {
        $this->stateMachineRegistry->transition(new Transition(PaymentOrderTransactionDefinition::ENTITY_NAME, $transactionId, $transition, 'stateId'), $context);
    }

    private function initialStateId(string $stateMachine, Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine($stateMachine, $context)->getInitialStateId()
            ?? throw PaymentException::invalidRequest('Payment state machine has no initial state.');
    }

    private function loadOrder(string $orderId, Context $context): PaymentOrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $this->addOrderAssociations($criteria);
        $order = $this->paymentOrderRepository->search($criteria, $context)->getEntities()->first();

        return $order instanceof PaymentOrderEntity ? $order : throw PaymentException::orderNotFound($orderId);
    }

    private function loadTransaction(string $transactionId, Context $context): PaymentOrderTransactionEntity
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('state');
        $transaction = $this->paymentOrderTransactionRepository->search($criteria, $context)->getEntities()->first();

        return $transaction instanceof PaymentOrderTransactionEntity ? $transaction : throw PaymentException::orderNotFound($transactionId);
    }

    private function findByExternalOrderNo(string $appId, string $externalOrderNo, Context $context): ?PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $appId));
        $criteria->addFilter(new EqualsFilter('externalOrderNo', $externalOrderNo));
        $this->addOrderAssociations($criteria);
        $criteria->setLimit(1);
        $order = $this->paymentOrderRepository->search($criteria, $context)->getEntities()->first();

        return $order instanceof PaymentOrderEntity ? $order : null;
    }

    private function addOrderAssociations(Criteria $criteria): void
    {
        $criteria->addAssociation('state');
        $criteria->addAssociation('primaryTransaction');
        $criteria->addAssociation('primaryTransaction.state');
    }

    private function resultFromOrder(PaymentOrderEntity $order): PaymentResult
    {
        $status = $order->state?->getTechnicalName() ?? PaymentStatus::UNKNOWN;
        $result = PaymentResult::fromArray($order->primaryTransaction?->responseData, $status);

        return $result->withResource($order->orderNo, $order->externalOrderNo, $order->primaryTransaction?->transactionNo);
    }

    private function validatePaymentRequest(PaymentRequest $request): void
    {
        if ($request->externalOrderNo === '' || $request->amount <= 0 || $request->method === '' || $request->subject === '') {
            throw PaymentException::invalidRequest('Payment order number, positive amount, method and subject are required.');
        }
        if (\strlen($request->currencyCode) !== 3) {
            throw PaymentException::invalidRequest('Payment currency code must contain three characters.');
        }
    }

    private function assertSamePayment(PaymentOrderEntity $order, PaymentRequest $request): void
    {
        if ($order->amount !== $request->amount || $order->currencyCode !== strtoupper($request->currencyCode) || $order->methodCode !== $request->method) {
            throw PaymentException::duplicateReference($request->externalOrderNo);
        }
    }

    private function dispatchStarted(PaymentOrderEntity $order, string $operation, Context $context): void
    {
        $this->eventDispatcher->dispatch(new PaymentGatewayCallStartedEvent(PaymentOrderDefinition::ENTITY_NAME, $order->getId(), $order->orderNo, $operation, $order->channelCode, $order->channelConfigId, $context));
    }

    private function dispatchCompleted(PaymentOrderEntity $order, string $operation, PaymentResult $result, Context $context): void
    {
        $this->eventDispatcher->dispatch(new PaymentGatewayCallCompletedEvent(PaymentOrderDefinition::ENTITY_NAME, $order->getId(), $order->orderNo, $operation, $order->channelCode, $order->channelConfigId, $result, $context));
    }

    private function dispatchFailed(PaymentOrderEntity $order, string $operation, \Throwable $exception, Context $context): void
    {
        $this->eventDispatcher->dispatch(new PaymentGatewayCallFailedEvent(PaymentOrderDefinition::ENTITY_NAME, $order->getId(), $order->orderNo, $operation, $order->channelCode, $order->channelConfigId, $exception, $context));
    }
}
