<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

/**
 * @internal
 */
final class FirstAvailableRouteStrategy implements PaymentRouteSelectionStrategyInterface
{
    public function select(array $candidates, PaymentRoutingContext $routing): ?PaymentRoute
    {
        return $candidates[0] ?? null;
    }
}
