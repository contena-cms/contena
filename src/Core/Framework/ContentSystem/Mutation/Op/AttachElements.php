<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;

/**
 * @internal
 */
final class AttachElements extends AbstractLayoutMutation
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
        foreach ($this->elements as $offset => $element) {
            $attach = new AttachElement(
                $this->registry,
                $element,
                $this->bindingRegistry,
                $this->bindingApplicator,
                $this->parentElementId,
                $this->slot,
                $this->index === null ? null : $this->index + $offset,
            );

            $tree = $attach->apply($tree);
            $this->affected = array_merge($this->affected, $attach->affected());
        }

        $this->created = $this->affected;

        return $tree;
    }
}
