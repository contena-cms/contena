<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation;

use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;

/**
 * Where an element sits in a tree: the node itself, its index within its containing list, and its parent slot
 * coordinates. $parent is null for a root element (then $index is the index in the root list).
 *
 * @internal
 */
final readonly class ElementLocation
{
    public function __construct(
        public ContentElement $node,
        public int $index,
        public ?ParentSlot $parent = null,
    ) {
    }
}
