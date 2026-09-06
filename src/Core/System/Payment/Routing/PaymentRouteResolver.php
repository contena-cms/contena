<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Event\PaymentRouteCandidateEvent;
use Contena\Core\System\Payment\Event\PaymentRouteResolvedEvent;
use Contena\Core\System\Payment\PaymentException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class PaymentRouteResolver extends AbstractPaymentRouteResolver
{
    /**
     * @param iterable<PaymentRouteProviderInterface> $providers
     * @param iterable<PaymentRouteSelectionStrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly iterable $strategies,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractPaymentRouteResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function resolve(PaymentAppEntity $app, Context $context, PaymentRoutingRequest $request): PaymentRoute
    {
        if (!$app->status || $app->tenantId !== $context->getTenantId()) {
            throw PaymentException::appNotFound($app->appCode);
        }
        if ($context->hasGlobalTenantAccess()) {
            throw PaymentException::invalidRequest('Payment operations require a platform or tenant context.');
        }
        $routing = new PaymentRoutingContext($app, $request, $context);
        $candidates = [];
        $seen = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->provide($routing) as $route) {
                $key = $route->gateway->code() . ':' . $route->channelConfigId;
                if (isset($seen[$key]) || !$route->gateway instanceof ($request->capability)
                    || ($request->preferredChannel !== null && $route->gateway->code() !== $request->preferredChannel)
                ) {
                    continue;
                }

                $event = new PaymentRouteCandidateEvent($route, $routing);
                $this->eventDispatcher->dispatch($event);
                $seen[$key] = true;
                if ($event->eligible) {
                    $candidates[] = $route;
                }
            }
        }

        $selected = null;
        foreach ($this->strategies as $strategy) {
            $selected = $strategy->select($candidates, $routing);
            if ($selected === null) {
                continue;
            }
            if (!\in_array($selected, $candidates, true)) {
                throw PaymentException::invalidRequest('A routing strategy must select an eligible payment route.');
            }

            break;
        }
        $selected ??= $candidates[0] ?? null;
        if ($selected === null) {
            throw PaymentException::routeNotFound($app->getId(), $request->methodCode);
        }

        $this->eventDispatcher->dispatch(new PaymentRouteResolvedEvent($selected, $routing));

        return $selected;
    }
}
