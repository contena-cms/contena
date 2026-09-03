<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\Event\PaymentGatewayCallCompletedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayCallFailedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayCallStartedEvent;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class PaymentRefundService
{
    /**
     * @param EntityRepository<PaymentRefundCollection> $paymentRefundRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentRefundRepository,
        private readonly PaymentOrderService $paymentOrderService,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult
    {
        if ($request->externalRefundNo === '' || $request->amount <= 0) {
            throw PaymentException::invalidRequest('External refund number and positive refund amount are required.');
        }

        $order = $this->paymentOrderService->findOrder($app->getId(), new QueryRequest($request->orderNo, $request->externalOrderNo), $context);
        if ($order->state?->getTechnicalName() !== PaymentOrderStates::STATE_SUCCEEDED) {
            throw PaymentException::orderNotSucceeded($order->orderNo);
        }

        $existing = $this->findRefund($order->getId(), $request->externalRefundNo, $context);
        if ($existing instanceof PaymentRefundEntity) {
            if ($existing->refundAmount !== $request->amount) {
                throw PaymentException::duplicateReference($request->externalRefundNo);
            }

            return $this->resultFromRefund($existing);
        }

        $route = $this->routeResolver->resolveConfigured($order->channelCode, $order->channelConfigId, PaymentOperation::REFUND, $context);
        if (!$route->gateway instanceof RefundHandlerInterface) {
            throw PaymentException::capabilityNotSupported($order->channelCode, PaymentOperation::REFUND);
        }

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

            if ($this->reserveRefundAmount($order, $request->amount, $context) !== 1) {
                throw PaymentException::refundAmountExceeded($request->amount);
            }
        });

        $refund = $this->loadRefund($refundId, $context);
        $this->dispatchStarted($refund, $order, $context);

        try {
            $result = $route->gateway->refund($refund, $order, $route->config);
        } catch (\Throwable $exception) {
            $this->paymentRefundRepository->update([[
                'id' => $refund->getId(),
                'status' => PaymentRefundStatus::STATUS_PROCESSING,
                'resultMessage' => $exception->getMessage(),
            ]], $context);
            $this->dispatchFailed($refund, $order, $exception, $context);

            throw $exception;
        }

        $this->persistResult($refund, $order, $result, $context);
        $this->dispatchCompleted($refund, $order, $result, $context);

        return $result->withResource($refund->refundNo, $refund->externalRefundNo);
    }

    private function persistResult(PaymentRefundEntity $refund, PaymentOrderEntity $order, PaymentResult $result, Context $context): void
    {
        $failed = \in_array($result->status, [PaymentStatus::FAILED, PaymentStatus::CLOSED], true);
        $succeeded = $result->status === PaymentStatus::SUCCEEDED;

        $this->connection->transactional(function () use ($refund, $order, $result, $failed, $succeeded, $context): void {
            $this->paymentRefundRepository->update([[
                'id' => $refund->getId(),
                'status' => $failed ? PaymentRefundStatus::STATUS_FAILED : ($succeeded ? PaymentRefundStatus::STATUS_SUCCEEDED : PaymentRefundStatus::STATUS_PROCESSING),
                'channelRefundNo' => $result->providerResourceId,
                'successTime' => $succeeded ? $this->clock->now() : null,
                'responseData' => $result->toArray(),
                'resultCode' => $result->resultCode,
                'resultMessage' => $result->resultMessage,
            ]], $context);

            if ($failed) {
                $this->releaseRefundAmount($order, $refund->refundAmount, $context);
            }
        });
    }

    private function reserveRefundAmount(PaymentOrderEntity $order, int $amount, Context $context): int
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

    private function releaseRefundAmount(PaymentOrderEntity $order, int $amount, Context $context): void
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
        if ($context->getTenantId() === null) {
            return ['`tenant_id` IS NULL', []];
        }

        return ['`tenant_id` = :tenantId', ['tenantId' => Uuid::fromHexToBytes($context->getTenantId())]];
    }

    private function findRefund(string $orderId, string $externalRefundNo, Context $context): ?PaymentRefundEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('externalRefundNo', $externalRefundNo));
        $criteria->setLimit(1);
        $refund = $this->paymentRefundRepository->search($criteria, $context)->getEntities()->first();

        return $refund instanceof PaymentRefundEntity ? $refund : null;
    }

    private function loadRefund(string $refundId, Context $context): PaymentRefundEntity
    {
        $refund = $this->paymentRefundRepository->search(new Criteria([$refundId]), $context)->getEntities()->first();

        return $refund instanceof PaymentRefundEntity ? $refund : throw PaymentException::invalidRequest('Payment refund could not be loaded.');
    }

    private function resultFromRefund(PaymentRefundEntity $refund): PaymentResult
    {
        $status = match ($refund->status) {
            PaymentRefundStatus::STATUS_SUCCEEDED => PaymentStatus::SUCCEEDED,
            PaymentRefundStatus::STATUS_FAILED => PaymentStatus::FAILED,
            default => PaymentStatus::PROCESSING,
        };

        return PaymentResult::fromArray($refund->responseData, $status)
            ->withResource($refund->refundNo, $refund->externalRefundNo);
    }

    private function dispatchStarted(PaymentRefundEntity $refund, PaymentOrderEntity $order, Context $context): void
    {
        $this->eventDispatcher->dispatch(new PaymentGatewayCallStartedEvent(PaymentRefundDefinition::ENTITY_NAME, $refund->getId(), $refund->refundNo, PaymentOperation::REFUND, $order->channelCode, $order->channelConfigId, $context));
    }

    private function dispatchCompleted(PaymentRefundEntity $refund, PaymentOrderEntity $order, PaymentResult $result, Context $context): void
    {
        $this->eventDispatcher->dispatch(new PaymentGatewayCallCompletedEvent(PaymentRefundDefinition::ENTITY_NAME, $refund->getId(), $refund->refundNo, PaymentOperation::REFUND, $order->channelCode, $order->channelConfigId, $result, $context));
    }

    private function dispatchFailed(PaymentRefundEntity $refund, PaymentOrderEntity $order, \Throwable $exception, Context $context): void
    {
        $this->eventDispatcher->dispatch(new PaymentGatewayCallFailedEvent(PaymentRefundDefinition::ENTITY_NAME, $refund->getId(), $refund->refundNo, PaymentOperation::REFUND, $order->channelCode, $order->channelConfigId, $exception, $context));
    }
}
