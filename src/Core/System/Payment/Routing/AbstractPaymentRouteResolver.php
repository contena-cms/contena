<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\Struct\PaymentRoute;

abstract class AbstractPaymentRouteResolver
{
    abstract public function getDecorated(): self;

    abstract public function resolve(string $appId, string $operation, Context $context, ?string $method = null, ?string $preferredChannel = null): PaymentRoute;
}
