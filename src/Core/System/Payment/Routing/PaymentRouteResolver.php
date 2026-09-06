<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Event\PaymentRouteCandidateEvent;
use Contena\Core\System\Payment\Event\PaymentRouteResolvedEvent;
use Contena\Core\System\Payment\PaymentAppGuard;
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
        private readonly PaymentAppGuard $appGuard,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractPaymentRouteResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function resolve(PaymentAppEntity $app, Context $context, PaymentRoutingRequest $request): PaymentRoute
    {
        $this->appGuard->validate($app, $context);
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

        foreach ($this->strategies as $strategy) {
            $route = $strategy->select($candidates, $routing);
            if ($route === null) {
                continue;
            }
            if (!\in_array($route, $candidates, true)) {
                throw PaymentException::invalidRequest('A routing strategy must select an eligible payment route.');
            }

            $this->eventDispatcher->dispatch(new PaymentRouteResolvedEvent($route, $routing));

            return $route;
        }

        throw PaymentException::routeNotFound($app->getId(), $request->methodCode);
    }
}
