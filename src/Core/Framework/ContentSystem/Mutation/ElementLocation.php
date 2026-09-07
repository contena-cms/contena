<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation;

use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;

/**
 * Where an element sits in a tree: the node itself, its index within its containing list, and its parent slot
 * coordinates. $parent is null for a root element (then $index is the index in the root list).
 *
 * Built from {@see StoredTree::locate()}'s array by
 * {@see AbstractLayoutMutation::locate()}; that array stays the single source and this is a typed view of it.
 *
 * @internal
 */
final readonly class ElementLocation
{
    public function __construct(
        public StoredElement $node,
        public int $index,
        public ?ParentSlot $parent = null,
    ) {
    }
}
