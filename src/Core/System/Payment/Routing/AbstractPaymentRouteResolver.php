<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;

abstract class AbstractPaymentRouteResolver
{
    abstract public function getDecorated(): self;

    abstract public function resolve(
        PaymentAppEntity $app,
        Context $context,
        PaymentRoutingRequest $request,
    ): PaymentRoute;
}
