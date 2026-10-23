<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Instrumentation;


/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 *
 * @codeCoverageIgnore - value object
 */
final readonly class Span
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public string $name,
        public string $category = 'contena',
        public array $tags = [],
    ) {
    }
}
