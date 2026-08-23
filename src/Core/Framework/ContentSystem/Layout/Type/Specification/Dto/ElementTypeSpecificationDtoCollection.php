<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
final readonly class ElementTypeSpecificationDtoCollection
{
    /**
     * Keyed by element type name (e.g. "CT:Product:Card") so Symfony includes
     * the name in violation property paths: types[CT:Product:Card].label
     *
     * @param array<string, ElementTypeSpecificationDto> $types
     */
    public function __construct(
        #[Assert\Valid]
        public array $types,
    ) {
    }
}
