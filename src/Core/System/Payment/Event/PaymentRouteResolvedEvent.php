<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Symfony\Contracts\EventDispatcher\Event;

final class PaymentRouteResolvedEvent extends Event
{
    public function __construct(
        public PaymentRoute $route,
        public readonly string $appId,
        public readonly string $operation,
        public readonly ?string $method,
        public readonly Context $context,
    ) {
    }
}
