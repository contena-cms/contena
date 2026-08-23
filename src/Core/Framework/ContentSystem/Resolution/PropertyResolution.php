<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Resolution;

/**
 * The resolution of a single declared property of an element type at a position: how it is (or is not) filled.
 *
 * @internal
 */
final readonly class PropertyResolution
{
    /**
     * @param list<ResolutionCandidate> $candidates
     */
    public function __construct(
        public string $key,
        public PropertyKind $kind,
        public bool $required,
        public ?string $type = null,
        public mixed $default = null,
        public ?string $fqcn = null,
        public ?ResolutionCandidate $resolved = null,
        public array $candidates = [],
    ) {
    }
}
