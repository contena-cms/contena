<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Loader;

use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedElementTypeSpecificationDto
{
    public function __construct(
        public string $name,
        public string $source,
        public ElementTypeSpecificationDto $dto,
    ) {
    }

    public function toSpecification(): ContentSystemElementTypeSpecification
    {
        return $this->dto->toContentSystemElementTypeSpecification($this->name, $this->source);
    }
}
