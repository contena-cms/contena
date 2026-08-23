<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing\Telemetry;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware as DriverMiddleware;
use Contena\Core\Framework\Telemetry\Doctrine\QueryCounter;
use Contena\Core\Framework\Telemetry\Doctrine\QueryCountMiddleware;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Contena\Core\Framework\Telemetry\Telemetry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Emits per-main-request HTTP metrics on kernel.terminate, which fires once per main request
 * (sub-requests excluded). Resolves the `area`, `domain` and `operation` labels once and reuses them
 * across all metrics.
 *
 * The labels come from the routed request. On a Channel request the kernel routes a duplicated
 * request (see RequestTransformer), and kernel.terminate gets the pre-transform request without route
 * attributes. So we keep the routed request from kernel.response and resolve labels from it on terminate.
 *
 * @internal
 */
final class HttpRequestMetricSubscriber implements EventSubscriberInterface
{
    private readonly ?QueryCounter $queryCounter;

    private ?Request $routedRequest = null;

    public function __construct(
        private readonly Telemetry $telemetry,
        private readonly AreaResolver $areaResolver,
        private readonly DomainResolver $domainResolver,
        private readonly OperationResolver $operationResolver,
        Connection $connection,
    ) {
        // Locate the shared counter from the live connection's middleware list (same approach as ConnectionProfiler).
        $middleware = current(array_filter(
            $connection->getConfiguration()->getMiddlewares(),
            static fn (DriverMiddleware $middleware): bool => $middleware instanceof QueryCountMiddleware
        ));

        $this->queryCounter = $middleware instanceof QueryCountMiddleware ? $middleware->getCounter() : null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Skip ESI fragments: they are resolved as main requests too. The outer
        // page request is the one without the `_sw_esi` marker.
        if (!$event->isMainRequest() || $event->getRequest()->attributes->has('_sw_esi')) {
            return;
        }

        $this->routedRequest = $event->getRequest();
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        // Use the routed request from kernel.response; fall back to the terminate request if missing.
        $request = $this->routedRequest ?? $event->getRequest();
        $this->routedRequest = null;

        $labels = [
            'area' => $this->areaResolver->resolve($request),
            'domain' => $this->domainResolver->resolve($request),
            'operation' => $this->operationResolver->resolve($request),
        ];

        $requestStart = $request->server->get('REQUEST_TIME_FLOAT');
        if ($requestStart !== null) {
            $this->telemetry->emit(new ConfiguredMetric(
                name: 'http.server.request.duration',
                value: (microtime(true) - (float) $requestStart) * 1000,
                labels: $labels + ['status_class' => $this->statusClass($event->getResponse()->getStatusCode())],
            ));
        }

        if ($this->queryCounter !== null) {
            $this->telemetry->emit(new ConfiguredMetric(
                name: 'http.server.request.queries.count',
                value: $this->queryCounter->reset(),
                labels: $labels,
            ));
        }

        $this->telemetry->emit(new ConfiguredMetric(
            name: 'http.server.request.memory.peak',
            value: memory_get_peak_usage(true),
            labels: $labels,
        ));
    }

    private function statusClass(int $statusCode): string
    {
        return intdiv($statusCode, 100) . 'xx';
    }
}
