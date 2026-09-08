<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Telemetry;

use Contena\Core\Framework\App\Event\AppInstalledEvent;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AppTelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'emitAppInstalledMetric',
        ];
    }

    public function emitAppInstalledMetric(): void
    {
        $this->meter->emit(new ConfiguredMetric(name: 'app.install.count', value: 1));
    }
}
