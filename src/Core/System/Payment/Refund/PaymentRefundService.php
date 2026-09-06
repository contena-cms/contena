<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Refund;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Refund\Struct\RefundRequest;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentRefundService extends AbstractPaymentRefundService
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentRefundCollection> $paymentRefundRepository
     */
    public function __construct(
        private readonly PaymentRefundPersister $persister,
        private readonly PaymentRefundStateHandler $stateHandler,
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $paymentRefundRepository,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
    ) {
    }

    public function getDecorated(): AbstractPaymentRefundService
    {
        throw new DecorationPatternException(self::class);
    }

    public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult
    {
        if (!$app->status || $app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }
        if (trim($request->orderNo ?? '') === '' && trim($request->externalOrderNo ?? '') === '') {
            throw PaymentException::orderNotFound('');
        }

        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new EqualsFilter('paymentAppId', $app->getId()));
        if (trim($request->orderNo ?? '') !== '') {
            $orderCriteria->addFilter(new EqualsFilter('orderNo', $request->orderNo));
        }
        if (trim($request->externalOrderNo ?? '') !== '') {
            $orderCriteria->addFilter(new EqualsFilter('externalOrderNo', $request->externalOrderNo));
        }
        $orderCriteria->addAssociation('state');
        $orderCriteria->setLimit(1);
        $order = $this->paymentOrderRepository->search($orderCriteria, $context)->getEntities()->first()
            ?? throw PaymentException::orderNotFound($request->orderNo ?? $request->externalOrderNo ?? '');
        if ($order->state?->getTechnicalName() !== PaymentOrderStates::STATE_SUCCEEDED) {
            throw PaymentException::orderNotSucceeded($order->orderNo);
        }

        $refundCriteria = new Criteria()
            ->addFilter(new EqualsFilter('orderId', $order->getId()))
            ->addFilter(new EqualsFilter('externalRefundNo', $request->externalRefundNo))
            ->setLimit(1);
        $existingRefund = $this->paymentRefundRepository->search($refundCriteria, $context)->getEntities()->first();
        if ($existingRefund instanceof PaymentRefundEntity) {
            if ($existingRefund->refundAmount !== $request->amount) {
                throw PaymentException::duplicateReference($request->externalRefundNo);
            }
            $status = match ($existingRefund->status) {
                PaymentRefundStatus::STATUS_SUCCEEDED => PaymentStatus::SUCCEEDED,
                PaymentRefundStatus::STATUS_FAILED => PaymentStatus::FAILED,
                default => PaymentStatus::PROCESSING,
            };

            return new PaymentResult(
                $existingRefund->refundNo,
                $existingRefund->externalRefundNo,
                GatewayResult::fromArray($existingRefund->responseData, $status),
            );
        }

        $route = $this->gatewayResolver->resolve($order->channelConfigId, $context);
        if (!$route->gateway instanceof RefundHandlerInterface) {
            throw PaymentException::capabilityNotSupported($order->channelCode, PaymentOperation::REFUND);
        }

        $refundId = $this->persister->persist([
            'orderId' => $order->getId(),
            'externalRefundNo' => $request->externalRefundNo,
            'refundAmount' => $request->amount,
            'channelCode' => $order->channelCode,
            'reason' => $request->reason,
        ], $context);
        $refund = $this->paymentRefundRepository->search(new Criteria([$refundId]), $context)->getEntities()->first()
            ?? throw PaymentException::refundNotFound($refundId);
        try {
            $gatewayResult = $this->gatewayExecutor->execute(
                PaymentOperation::REFUND,
                new PaymentEntityReference(PaymentRefundDefinition::ENTITY_NAME, $refund->getId()),
                $route,
                $context,
                fn (): GatewayResult => $route->gateway->refund($refund, $order, $route->config),
            );
        } catch (\Throwable $exception) {
            $this->stateHandler->recordFailure($refund, $exception, $context);
            throw $exception;
        }

        $gatewayResult = $this->stateHandler->apply($refund, $order, $gatewayResult, $context);

        return new PaymentResult($refund->refundNo, $refund->externalRefundNo, $gatewayResult);
    }
}
