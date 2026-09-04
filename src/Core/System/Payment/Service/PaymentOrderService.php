<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
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
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

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
        private readonly PaymentOrderPersister $paymentOrderPersister,
        private readonly PaymentOrderStateHandler $paymentOrderStateHandler,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult
    {
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

        $creation = $this->paymentOrderPersister->persist($app, $request, $route, $context);

        $order = $this->loadOrder($creation->orderId, $context);
        $transaction = $this->loadTransaction($creation->transactionId, $context);

        return $this->executePayment($order, $transaction, $route, $context);
    }

    public function query(PaymentAppEntity $app, QueryRequest $request, Context $context): PaymentResult
    {
        $order = $this->findOrder($app->getId(), $request, $context);
        $route = $this->routeResolver->resolveConfigured($order->channelCode, $order->channelConfigId, PaymentOperation::QUERY, $context);
        if (!$route->gateway instanceof QueryHandlerInterface) {
            throw PaymentException::capabilityNotSupported($order->channelCode, PaymentOperation::QUERY);
        }

        $transactionId = $this->paymentOrderPersister->persistQueryTransaction($order, $context);

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
            $this->paymentOrderStateHandler->applyOrderStatus($order, $result->status, $target->context);
        });
    }

    private function executePayment(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, PaymentRoute $route, Context $context): PaymentResult
    {
        try {
            \assert($route->gateway instanceof PaymentHandlerInterface);
            $result = $route->gateway->pay($order, $route->config);
        } catch (\Throwable $exception) {
            $this->persistFailure($order, $transaction, $exception, $context, true);
            throw $exception;
        }

        $this->persistResult($order, $transaction, $result, $context, true);

        return $result->withResource($order->orderNo, $order->externalOrderNo, $transaction->transactionNo);
    }

    private function executeQuery(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, PaymentRoute $route, Context $context): PaymentResult
    {
        try {
            \assert($route->gateway instanceof QueryHandlerInterface);
            $result = $route->gateway->query($order, $route->config);
        } catch (\Throwable $exception) {
            $this->persistFailure($order, $transaction, $exception, $context, false);
            throw $exception;
        }

        $this->persistResult($order, $transaction, $result, $context, false);

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
            $this->paymentOrderStateHandler->applyTransactionStatus($transaction->getId(), $result->status, $context);

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
            $this->paymentOrderStateHandler->applyOrderStatus($order, $result->status, $context);
        });
    }

    private function persistFailure(PaymentOrderEntity $order, PaymentOrderTransactionEntity $transaction, \Throwable $exception, Context $context, bool $primary): void
    {
        $this->connection->transactional(function () use ($order, $transaction, $exception, $context, $primary): void {
            $this->paymentOrderTransactionRepository->update([[
                'id' => $transaction->getId(),
                'resultMessage' => $exception->getMessage(),
            ]], $context);
            if ($primary) {
                $this->paymentOrderRepository->update([[
                    'id' => $order->getId(),
                    'primaryTransactionId' => $transaction->getId(),
                ]], $context);
            }
            $this->paymentOrderStateHandler->applyOrderStatus($order, PaymentStatus::UNKNOWN, $context);
        });
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

    private function assertSamePayment(PaymentOrderEntity $order, PaymentRequest $request): void
    {
        if ($order->amount !== $request->amount || $order->currencyCode !== strtoupper($request->currencyCode) || $order->methodCode !== $request->method) {
            throw PaymentException::duplicateReference($request->externalOrderNo);
        }
    }
}
