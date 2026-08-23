<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Instrumentation;

/**
 * @codeCoverageIgnore - value object
 */
final readonly class DurationMetric
{
    /**
     * @param array<non-empty-string, string|bool|float|int> $labels
     */
    public function __construct(
        public string $name,
        public array $labels = [],
    ) {
    }
}
