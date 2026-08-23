<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Resolution;

/**
 * @internal
 */
final readonly class ResolutionContext
{
    /**
     * @param list<ProvidedContext> $available context available AT this element's position
     */
    public function __construct(
        public string $elementId,
        public array $available,
    ) {
    }
}
