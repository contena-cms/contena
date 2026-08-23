<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;

/**
 * Deletes $elementId and its whole subtree. Surviving elements' wiring is left untouched, and no
 * surviving element is affected.
 *
 * @internal
 */
final class RemoveElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
    ) {
    }

    public function apply(array $tree): array
    {
        if ($this->findNode($tree, $this->elementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        return $this->removeSubtree($tree, $this->elementId);
    }
}
