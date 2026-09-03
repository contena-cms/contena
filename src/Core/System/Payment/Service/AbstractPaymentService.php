<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Service;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Struct\TransferRequest;

abstract class AbstractPaymentService
{
    abstract public function getDecorated(): self;

    abstract public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult;

    abstract public function query(PaymentAppEntity $app, QueryRequest $request, Context $context): PaymentResult;

    abstract public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult;

    abstract public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult;

    abstract public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult;
}
