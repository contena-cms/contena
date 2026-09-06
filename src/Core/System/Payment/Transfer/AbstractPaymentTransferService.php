<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Transfer;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Transfer\Struct\TransferRequest;

abstract class AbstractPaymentTransferService
{
    abstract public function getDecorated(): self;

    abstract public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult;
}
