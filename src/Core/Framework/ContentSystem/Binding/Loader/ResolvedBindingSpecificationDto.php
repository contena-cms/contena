<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Binding\Loader;

use Contena\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Contena\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;

/**
 * @internal
 */
final readonly class ResolvedBindingSpecificationDto
{
    public function __construct(
        public string $id,
        public string $source,
        public BindingSpecificationDto $dto,
    ) {
    }

    public function toSpecification(): BindingSpecification
    {
        return $this->dto->toBindingSpecification($this->id, $this->source);
    }
}
