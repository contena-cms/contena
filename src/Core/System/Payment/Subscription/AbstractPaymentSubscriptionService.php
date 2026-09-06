<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Subscription;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Subscription\Struct\SubscriptionRequest;

abstract class AbstractPaymentSubscriptionService
{
    abstract public function getDecorated(): self;

    abstract public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult;
}
