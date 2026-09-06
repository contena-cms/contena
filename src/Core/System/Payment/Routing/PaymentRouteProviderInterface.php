<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

interface PaymentRouteProviderInterface
{
    final public const string SERVICE_TAG = 'contena.payment.route_provider';

    /**
     * Supply candidates in preference order. Providers must respect the app and
     * tenant scope; platform fallback is only for shared channel configurations.
     *
     * @return iterable<PaymentRoute>
     */
    public function provide(PaymentRoutingContext $routing): iterable;
}
