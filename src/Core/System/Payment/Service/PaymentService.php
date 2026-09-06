<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Payment\AbstractPaymentOrderService;
use Contena\Core\System\Payment\Payment\Struct\OrderReference;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Refund\AbstractPaymentRefundService;
use Contena\Core\System\Payment\Refund\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Subscription\AbstractPaymentSubscriptionService;
use Contena\Core\System\Payment\Subscription\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Transfer\AbstractPaymentTransferService;
use Contena\Core\System\Payment\Transfer\Struct\TransferRequest;

/**
 * @internal
 */
class PaymentService extends AbstractPaymentService
{
    public function __construct(
        private readonly AbstractPaymentOrderService $paymentOrderService,
        private readonly AbstractPaymentRefundService $paymentRefundService,
        private readonly AbstractPaymentTransferService $paymentTransferService,
        private readonly AbstractPaymentSubscriptionService $paymentSubscriptionService,
    ) {
    }

    public function getDecorated(): AbstractPaymentService
    {
        throw new DecorationPatternException(self::class);
    }

    public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult
    {
        return $this->paymentOrderService->pay($app, $request, $context);
    }

    public function query(PaymentAppEntity $app, OrderReference $request, Context $context): PaymentResult
    {
        return $this->paymentOrderService->query($app, $request, $context);
    }

    public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult
    {
        return $this->paymentRefundService->refund($app, $request, $context);
    }

    public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult
    {
        return $this->paymentTransferService->transfer($app, $request, $context);
    }

    public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult
    {
        return $this->paymentSubscriptionService->subscribe($app, $request, $context);
    }
}
