<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Refund;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\Payment\PaymentOrderLoader;
use Contena\Core\System\Payment\Payment\Struct\OrderReference;
use Contena\Core\System\Payment\PaymentAppGuard;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Refund\Struct\RefundRequest;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
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
    public function __construct(
        private readonly PaymentRefundPersister $persister,
        private readonly PaymentOrderLoader $orderLoader,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
        private readonly PaymentAppGuard $appGuard,
    ) {
    }

    public function getDecorated(): AbstractPaymentRefundService
    {
        throw new DecorationPatternException(self::class);
    }

    public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult
    {
        $this->appGuard->validate($app, $context);

        $order = $this->orderLoader->load($app->getId(), new OrderReference($request->orderNo, $request->externalOrderNo), $context);
        if ($order->state?->getTechnicalName() !== PaymentOrderStates::STATE_SUCCEEDED) {
            throw PaymentException::orderNotSucceeded($order->orderNo);
        }

        $existingRefund = $this->persister->findRefund($order->getId(), $request->externalRefundNo, $context);
        if ($existingRefund instanceof PaymentRefundEntity) {
            if ($existingRefund->refundAmount !== $request->amount) {
                throw PaymentException::duplicateReference($request->externalRefundNo);
            }

            return $this->createResultForRefund($existingRefund);
        }

        $route = $this->gatewayResolver->resolve($order->channelConfigId, $context);
        if (!$route->gateway instanceof RefundHandlerInterface) {
            throw PaymentException::capabilityNotSupported($order->channelCode, PaymentOperation::REFUND);
        }

        $refund = $this->persister->createRefund($order, $request, $context);
        try {
            $result = $this->gatewayExecutor->execute(PaymentOperation::REFUND, $this->persister->reference($refund), $route, $context, fn (): PaymentResult => $route->gateway->refund($refund, $order, $route->config));
        } catch (\Throwable $exception) {
            $this->persister->recordFailure($refund, $exception, $context);
            throw $exception;
        }

        $result = $this->persister->persistResult($refund, $order, $result, $context);

        return $result->withResource($refund->refundNo, $refund->externalRefundNo);
    }

    private function createResultForRefund(PaymentRefundEntity $refund): PaymentResult
    {
        $status = match ($refund->status) {
            PaymentRefundStatus::STATUS_SUCCEEDED => PaymentStatus::SUCCEEDED,
            PaymentRefundStatus::STATUS_FAILED => PaymentStatus::FAILED,
            default => PaymentStatus::PROCESSING,
        };

        return PaymentResult::fromArray($refund->responseData, $status)
            ->withResource($refund->refundNo, $refund->externalRefundNo);
    }
}
