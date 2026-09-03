<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\System\Payment\Rule\PaymentRuleScope;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Symfony\Contracts\EventDispatcher\Event;

final class PaymentRouteResolvedEvent extends Event
{
    public function __construct(
        public PaymentRoute $route,
        public readonly PaymentRuleScope $scope,
    ) {
    }
}
