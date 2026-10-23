<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Telemetry;

use Contena\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CacheTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvalidateCacheEvent::class => 'emitInvalidateCacheCountMetric',
        ];
    }

    public function emitInvalidateCacheCountMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric('cache.invalidate.count', 1));
    }
}
