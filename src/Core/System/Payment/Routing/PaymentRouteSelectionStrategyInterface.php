<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

interface PaymentRouteSelectionStrategyInterface
{
    final public const string SERVICE_TAG = 'contena.payment.route_selection_strategy';

    /**
     * Return a supplied candidate, or null to let the next strategy decide.
     *
     * @param list<PaymentRoute> $candidates
     */
    public function select(array $candidates, PaymentRoutingContext $routing): ?PaymentRoute;
}
