<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Diagnostics;

use Contena\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;

/**
 * A single defect found in a layout, addressed by element id plus an optional property/context key.
 * Scope and severity are derived from the {@see ViolationCode}.
 *
 * @internal
 */
final readonly class Violation
{
    /**
     * @param list<ResolutionCandidate> $candidates
     */
    public function __construct(
        public ViolationCode $code,
        public string $elementId,
        public ?string $key,
        public string $message,
        public array $candidates = [],
    ) {
    }

    public function scope(): ViolationScope
    {
        return $this->code->scope();
    }

    public function severity(): ViolationSeverity
    {
        return $this->code->severity();
    }
}
