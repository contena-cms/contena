<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Struct\TransferRequest;

/**
 * @internal
 */
class PaymentService extends AbstractPaymentService
{
    public function __construct(
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentRefundService $paymentRefundService,
        private readonly PaymentTransferService $paymentTransferService,
        private readonly PaymentSubscriptionService $paymentSubscriptionService,
    ) {
    }

    public function getDecorated(): AbstractPaymentService
    {
        throw new DecorationPatternException(self::class);
    }

    public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult
    {
        $this->validateContext($app, $context);

        return $this->paymentOrderService->pay($app, $request, $context);
    }

    public function query(PaymentAppEntity $app, QueryRequest $request, Context $context): PaymentResult
    {
        $this->validateContext($app, $context);

        return $this->paymentOrderService->query($app, $request, $context);
    }

    public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult
    {
        $this->validateContext($app, $context);

        return $this->paymentRefundService->refund($app, $request, $context);
    }

    public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult
    {
        $this->validateContext($app, $context);

        return $this->paymentTransferService->transfer($app, $request, $context);
    }

    public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult
    {
        $this->validateContext($app, $context);

        return $this->paymentSubscriptionService->subscribe($app, $request, $context);
    }

    private function validateContext(PaymentAppEntity $app, Context $context): void
    {
        if (!$app->status) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment writes require a platform or tenant context.');
        }
        if ($app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
    }
}
