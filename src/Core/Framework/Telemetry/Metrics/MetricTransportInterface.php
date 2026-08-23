<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Metrics;

use Contena\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Contena\Core\Framework\Telemetry\Metrics\Metric\Metric;

interface MetricTransportInterface
{
    /**
     * @throws MetricNotSupportedException
     */
    public function emit(Metric $metric): void;

    /**
     * Called by the framework on `kernel.terminate` and `console.terminate`.
     * Push transports can use this to flush batched emissions; pull transports
     * can persist aggregated values. Implement as a no-op when not needed.
     */
    public function flush(): void;
}
