<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation;

use Contena\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyResolution;

/**
 * @internal
 */
final readonly class MutationResult
{
    /**
     * @param list<ContentElement> $layout
     * @param array<string, list<PropertyResolution>> $resolutions keyed by element id
     * @param list<string> $affectedElementIds
     * @param list<ContentElement> $orphaned subtrees detached by the op, returned so the caller can re-place them
     * @param list<string> $droppedWiring wiring keys the op dropped, reported so the caller can re-wire
     * @param array<string, mixed> $droppedProperties static property values the op could not carry over, keyed by property key
     */
    public function __construct(
        public array $layout,
        public array $resolutions,
        public DiagnosticsReport $diagnostics,
        public array $affectedElementIds,
        public array $orphaned = [],
        public array $droppedWiring = [],
        public array $droppedProperties = [],
    ) {
    }
}
