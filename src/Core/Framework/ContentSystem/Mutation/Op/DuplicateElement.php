<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;

/**
 * Deep-clones $elementId's subtree, reminting every node id, and splices the clone as the next sibling (or at
 * $index). Context wiring is key/position-based, never id-based, so it carries over unchanged with no internal
 * id references to rewrite.
 *
 * @internal
 */
final class DuplicateElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(array $tree): array
    {
        $location = $this->locate($tree, $this->elementId);

        if ($location === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $clone = $this->cloneWithNewIds($location->node);
        $this->affected = $this->subtreeIds($clone);

        $index = $this->index ?? $location->index + 1;

        if ($location->parent === null) {
            return $this->insertAtRoot($tree, $index, [$clone]);
        }

        return $this->insertIntoSlot($tree, $location->parent->parentId, $location->parent->slot, $index, [$clone]);
    }
}
