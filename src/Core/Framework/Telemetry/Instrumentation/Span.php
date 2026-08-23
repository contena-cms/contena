<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Instrumentation;

/**
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
