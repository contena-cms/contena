<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Binding\Serialization;

use Contena\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;

/**
 * The id is not part of the serialized shape — it comes from the YAML body's "id" key and is supplied
 * to the DTO separately by the loader.
 *
 * @internal
 *
 * @final
 */
class BindingSpecificationSerializer
{
    /**
     * @param array<string, mixed> $data
     */
    public function denormalize(array $data): BindingSpecificationDto
    {
        // Every facet is carried raw (the DTO types them as mixed) so a wrong-typed declaration is rejected
        // with a clean WellFormedBindingSpecification violation rather than silently coerced before
        // validation can see it.
        return new BindingSpecificationDto(
            type: $data['type'] ?? null,
            label: $data['label'] ?? null,
            resolves: $data['resolves'] ?? null,
            inputs: $data['inputs'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(BindingSpecificationDto $dto): array
    {
        return [
            'type' => $dto->type,
            'label' => $dto->label,
            'resolves' => $dto->resolves,
            'inputs' => $dto->inputs,
        ];
    }
}
