<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentQueryHandlerInterface;
use Contena\Core\System\Payment\Payment\Struct\OrderReference;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\PaymentAppGuard;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
class PaymentOrderService extends AbstractPaymentOrderService
{
    public function __construct(
        private readonly PaymentOrderPersister $persister,
        private readonly PaymentOrderLoader $orderLoader,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
        private readonly PaymentAppGuard $appGuard,
    ) {
    }

    public function getDecorated(): AbstractPaymentOrderService
    {
        throw new DecorationPatternException(self::class);
    }

    public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult
    {
        $this->appGuard->validate($app, $context);

        $existingOrder = $this->persister->find($app->getId(), $request->externalOrderNo, $context);

        if ($existingOrder !== null) {
            throw PaymentException::duplicateReference($request->externalOrderNo);
        }

        $route = $this->routeResolver->resolve($app, $context, new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class, $request->method, $request->channel, $request->amount, $request->currencyCode));
        if (!$route->gateway instanceof PaymentHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::PAY);
        }

        $orderId = $this->persister->persist($app, $request, $route, $context);
        $order = $this->persister->getOrderById($orderId, $context);
        $transaction = $this->persister->createPaymentTransaction($order, $context);

        try {
            $result = $this->gatewayExecutor->execute(PaymentOperation::PAY, $this->persister->reference($order), $route, $context, fn (): PaymentResult => $route->gateway->pay($order, $route->config));
        } catch (\Throwable $exception) {
            $this->persister->persistFailure($order, $transaction, $exception, $context);
            throw $exception;
        }

        $result = $this->persister->persistResult($order, $transaction, $result, $context);

        return $result->withResource($order->orderNo, $order->externalOrderNo, $transaction->transactionNo);
    }

    public function query(PaymentAppEntity $app, OrderReference $request, Context $context): PaymentResult
    {
        $this->appGuard->validate($app, $context);

        $order = $this->orderLoader->load($app->getId(), $request, $context);
        $route = $this->gatewayResolver->resolve($order->channelConfigId, $context);
        if (!$route->gateway instanceof PaymentQueryHandlerInterface) {
            throw PaymentException::capabilityNotSupported($order->channelCode, PaymentOperation::QUERY);
        }

        $transaction = $this->persister->createQueryTransaction($order, $context);

        try {
            $result = $this->gatewayExecutor->execute(PaymentOperation::QUERY, $this->persister->reference($order), $route, $context, fn (): PaymentResult => $route->gateway->query($order, $route->config));
        } catch (\Throwable $exception) {
            $this->persister->persistFailure($order, $transaction, $exception, $context);
            throw $exception;
        }

        $result = $this->persister->persistResult($order, $transaction, $result, $context);

        return $result->withResource($order->orderNo, $order->externalOrderNo, $transaction->transactionNo);
    }
}
