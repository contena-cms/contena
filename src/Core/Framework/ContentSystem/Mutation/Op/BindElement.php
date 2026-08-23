<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;

/**
 * Applies a registered binding specification's wiring onto one element via {@see BindingApplicator}, keeping the same
 * element id.
 *
 * @internal
 */
final class BindElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
        private readonly string $bindingSpecificationId,
        private readonly string $elementId,
        private readonly BindingApplicator $applicator,
    ) {
    }

    public function apply(array $tree): array
    {
        $specification = $this->registry->get($this->bindingSpecificationId);

        if ($specification === null) {
            throw ContentSystemException::bindingSpecificationNotFound($this->bindingSpecificationId);
        }

        $node = $this->findNode($tree, $this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        if ($specification->type() !== $node->getComponent()) {
            throw ContentSystemException::bindingTypeMismatch($this->bindingSpecificationId, $specification->type(), $node->getComponent());
        }

        $replacement = $this->applicator->apply($node, $specification, $this->bindingSpecificationId);

        $result = $this->replaceNode($tree, $this->elementId, $replacement);

        $this->affected = [$replacement->getId()];

        return $result;
    }
}
