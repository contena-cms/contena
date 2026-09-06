<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Contena\Core\System\Payment\Routing\PaymentRoutingContext;
use Symfony\Contracts\EventDispatcher\Event;

final class PaymentRouteCandidateEvent extends Event implements ContenaEvent
{
    public bool $eligible = true;

    public function __construct(
        public readonly PaymentRoute $route,
        public readonly PaymentRoutingContext $routing,
    ) {
    }

    public function getContext(): Context
    {
        return $this->routing->context;
    }
}
