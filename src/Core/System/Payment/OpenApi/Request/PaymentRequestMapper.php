<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Request;

use Contena\Core\System\Payment\Payment\Struct\OrderReference;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest as OrderPaymentRequest;
use Contena\Core\System\Payment\Refund\Struct\RefundRequest as DomainRefundRequest;
use Contena\Core\System\Payment\Subscription\Struct\SubscriptionRequest as DomainSubscriptionRequest;
use Contena\Core\System\Payment\Transfer\Struct\TransferRequest as DomainTransferRequest;
use Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see OpenApiTest
 */
final class PaymentRequestMapper
{
    public function payment(PaymentRequest $request, ?string $clientIp): OrderPaymentRequest
    {
        return new OrderPaymentRequest(
            externalOrderNo: $request->externalOrderNo,
            amount: $request->amount,
            method: $request->method,
            subject: $request->subject,
            currencyCode: $request->currencyCode,
            channel: $request->channel,
            deviceType: $request->deviceType,
            notifyUrl: $request->notifyUrl,
            returnUrl: $request->returnUrl,
            extra: $request->extra,
            clientIp: $clientIp,
        );
    }

    public function query(QueryRequest $request): OrderReference
    {
        return new OrderReference(
            orderNo: $request->orderNo,
            externalOrderNo: $request->externalOrderNo,
        );
    }

    public function refund(RefundRequest $request): DomainRefundRequest
    {
        return new DomainRefundRequest(
            externalRefundNo: $request->externalRefundNo,
            amount: $request->amount,
            orderNo: $request->orderNo,
            externalOrderNo: $request->externalOrderNo,
            reason: $request->reason,
        );
    }

    public function transfer(TransferRequest $request): DomainTransferRequest
    {
        return new DomainTransferRequest(
            externalTransferNo: $request->externalTransferNo,
            amount: $request->amount,
            currencyCode: $request->currencyCode,
            payee: $request->payee,
            payeeName: $request->payeeName,
            channel: $request->channel,
            remark: $request->remark,
            notifyUrl: $request->notifyUrl,
            extra: $request->extra,
        );
    }

    public function subscription(SubscriptionRequest $request): DomainSubscriptionRequest
    {
        return new DomainSubscriptionRequest(
            externalSubscriptionNo: $request->externalSubscriptionNo,
            channel: $request->channel,
            notifyUrl: $request->notifyUrl,
            returnUrl: $request->returnUrl,
            periodType: $request->periodType,
            period: $request->period,
            executeTime: $request->executeTime,
            singleAmount: $request->singleAmount,
            totalAmount: $request->totalAmount,
            totalPayments: $request->totalPayments,
            extra: $request->extra,
        );
    }
}
