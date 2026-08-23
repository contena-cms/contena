<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Specification;

/**
 * @internal
 *
 * @phpstan-type CopilotSchema = array{summary: string, hints: list<string>}
 */
final readonly class CopilotSpecification
{
    /**
     * @param list<string> $hints
     */
    public function __construct(
        private string $summary,
        private array $hints,
    ) {
    }

    /**
     * @return CopilotSchema
     */
    public function toSchema(): array
    {
        return [
            'summary' => $this->summary,
            'hints' => $this->hints,
        ];
    }
}
