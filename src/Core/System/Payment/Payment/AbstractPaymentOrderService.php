<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;

abstract class AbstractPaymentOrderService
{
    abstract public function getDecorated(): self;

    abstract public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult;

    abstract public function query(PaymentAppEntity $app, ?string $orderNo, ?string $externalOrderNo, Context $context): PaymentResult;
}
