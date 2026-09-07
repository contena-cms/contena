<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;

/**
 * @internal
 */
final class InsertPreset extends AbstractLayoutMutation
{
    /**
     * @param list<StoredElement> $elements
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly array $elements,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
        private readonly ?string $parentElementId = null,
        private readonly ?string $slot = null,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $clones = [];

        foreach ($this->elements as $element) {
            $this->requireRegistered($this->registry, $element->component);

            $clone = $this->applyDefaultBindings($this->cloneWithNewIds($element));
            $clones[] = $clone;
            $this->affected = array_merge($this->affected, $this->subtreeIds($clone));
        }

        $this->created = $this->affected;

        if ($this->parentElementId === null) {
            return $tree->insertAtRoot($this->index, $clones);
        }

        if ($this->slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        if ($tree->find($this->parentElementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->parentElementId);
        }

        return $tree->insertIntoSlot($this->parentElementId, $this->slot, $this->index, $clones);
    }

    private function applyDefaultBindings(StoredElement $element): StoredElement
    {
        $default = $this->resolveDefaultSpecification($this->bindingRegistry, $element->component);

        $bound = $default === null
            ? $element
            : $this->bindingApplicator->applyFillOnly($element, $default, $default->qualifiedId());

        $slots = [];
        foreach ($bound->slots as $name => $children) {
            $slots[$name] = array_values(array_map($this->applyDefaultBindings(...), $children));
        }

        return $bound->withSlots($slots);
    }
}
