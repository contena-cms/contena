<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Refund;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Refund\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;

abstract class AbstractPaymentRefundService
{
    abstract public function getDecorated(): self;

    abstract public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult;
}
