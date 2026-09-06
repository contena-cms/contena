<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\PaymentQueryHandlerInterface;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
use Contena\Core\System\Payment\Routing\PaymentRoutingRequest;
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
class PaymentOrderService extends AbstractPaymentOrderService
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentOrderTransactionCollection> $paymentOrderTransactionRepository
     */
    public function __construct(
        private readonly PaymentOrderPersister $persister,
        private readonly PaymentOrderConverter $converter,
        private readonly PaymentOrderStateHandler $stateHandler,
        private readonly EntityRepository $paymentOrderRepository,
        private readonly EntityRepository $paymentOrderTransactionRepository,
        private readonly AbstractPaymentRouteResolver $routeResolver,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly GatewayOperationExecutor $gatewayExecutor,
    ) {
    }

    public function getDecorated(): AbstractPaymentOrderService
    {
        throw new DecorationPatternException(self::class);
    }

    public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult
    {
        if (!$app->status || $app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('paymentAppId', $app->getId()))
            ->addFilter(new EqualsFilter('externalOrderNo', $request->externalOrderNo))
            ->setLimit(1);
        if ($this->paymentOrderRepository->searchIds($criteria, $context)->firstId() !== null) {
            throw PaymentException::duplicateReference($request->externalOrderNo);
        }

        $route = $this->routeResolver->resolve($app, $context, new PaymentRoutingRequest(PaymentOperation::PAY, PaymentHandlerInterface::class, $request->method, $request->channel, $request->amount, $request->currencyCode));
        if (!$route->gateway instanceof PaymentHandlerInterface) {
            throw PaymentException::capabilityNotSupported($route->gateway->code(), PaymentOperation::PAY);
        }

        $orderId = $this->persister->persist($this->converter->convert($app, $request, $route, $context), $context);
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociation('state');
        $order = $this->paymentOrderRepository->search($orderCriteria, $context)->getEntities()->first()
            ?? throw PaymentException::orderNotFound($orderId);

        $transactionId = $this->persister->persistPrimaryTransaction($order, $context);
        $transactionCriteria = new Criteria([$transactionId]);
        $transactionCriteria->addAssociation('state');
        $transaction = $this->paymentOrderTransactionRepository->search($transactionCriteria, $context)->getEntities()->first()
            ?? throw PaymentException::transactionNotFound($transactionId);

        try {
            $gatewayResult = $this->gatewayExecutor->execute(
                PaymentOperation::PAY,
                new PaymentEntityReference(PaymentOrderDefinition::ENTITY_NAME, $order->getId()),
                $route,
                $context,
                fn (): GatewayResult => $route->gateway->pay($order, $route->config),
            );
        } catch (\Throwable $exception) {
            $this->stateHandler->recordFailure($order, $transaction, $exception, $context);
            throw $exception;
        }

        $gatewayResult = $this->stateHandler->apply($order, $transaction, $gatewayResult, $context);

        return new PaymentResult($order->orderNo, $order->externalOrderNo, $gatewayResult, $transaction->transactionNo);
    }

    public function query(PaymentAppEntity $app, ?string $orderNo, ?string $externalOrderNo, Context $context): PaymentResult
    {
        if (!$app->status || $app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }
        if (trim($orderNo ?? '') === '' && trim($externalOrderNo ?? '') === '') {
            throw PaymentException::orderNotFound('');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentAppId', $app->getId()));
        if (trim($orderNo ?? '') !== '') {
            $criteria->addFilter(new EqualsFilter('orderNo', $orderNo));
        }
        if (trim($externalOrderNo ?? '') !== '') {
            $criteria->addFilter(new EqualsFilter('externalOrderNo', $externalOrderNo));
        }
        $criteria->addAssociation('state');
        $criteria->setLimit(1);
        $order = $this->paymentOrderRepository->search($criteria, $context)->getEntities()->first()
            ?? throw PaymentException::orderNotFound($orderNo ?? $externalOrderNo ?? '');

        $route = $this->gatewayResolver->resolve($order->channelConfigId, $context);
        if (!$route->gateway instanceof PaymentQueryHandlerInterface) {
            throw PaymentException::capabilityNotSupported($order->channelCode, PaymentOperation::QUERY);
        }

        $transactionId = $order->primaryTransactionId ?? throw PaymentException::transactionNotFound($order->orderNo);
        $transactionCriteria = new Criteria([$transactionId]);
        $transactionCriteria->addAssociation('state');
        $transaction = $this->paymentOrderTransactionRepository->search($transactionCriteria, $context)->getEntities()->first()
            ?? throw PaymentException::transactionNotFound($transactionId);
        $gatewayResult = $this->gatewayExecutor->execute(
            PaymentOperation::QUERY,
            new PaymentEntityReference(PaymentOrderDefinition::ENTITY_NAME, $order->getId()),
            $route,
            $context,
            fn (): GatewayResult => $route->gateway->query($order, $route->config),
        );
        $gatewayResult = $this->stateHandler->apply($order, $transaction, $gatewayResult, $context);

        return new PaymentResult($order->orderNo, $order->externalOrderNo, $gatewayResult, $transaction->transactionNo);
    }
}
