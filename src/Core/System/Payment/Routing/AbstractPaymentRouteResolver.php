<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\OpenApi\Api\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentRoute;

abstract class AbstractPaymentRouteResolver
{
    abstract public function getDecorated(): self;

    abstract public function resolve(
        PaymentAppEntity $app,
        Context $context,
        PaymentRequest $request,
    ): PaymentRoute;
}
