<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem;

use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;

final readonly class RenderableLayout
{
    /**
     * @param list<StoredElement> $elements
     */
    private function __construct(
        public LayoutReference $reference,
        public array $elements,
    ) {
    }

    /**
     * @param list<StoredElement> $elements
     */
    public static function create(LayoutReference $reference, array $elements): self
    {
        return new self($reference, $elements);
    }

    public static function fromEntity(ContentLayoutEntity $entity): self
    {
        return self::create(
            LayoutReference::fromEntity($entity),
            $entity->getLayout()
        );
    }
}
