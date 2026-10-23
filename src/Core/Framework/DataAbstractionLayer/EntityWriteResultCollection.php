<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

use Contena\Core\Framework\Struct\Collection;

/**
 * @template IDStructure of string|array<string, string> = string
 *
 * @extends Collection<EntityWriteResult<IDStructure>>
 */
class EntityWriteResultCollection extends Collection
{
    /**
     * @return self<IDStructure>
     */
    public function only(string ...$operations): self
    {
        return $this->filter(
            static fn (EntityWriteResult $result): bool => \in_array($result->getOperation(), $operations, true)
        );
    }

    /**
     * @return self<IDStructure>
     */
    public function withPayloadProperties(string ...$properties): self
    {
        return $this->filter(
            static fn (EntityWriteResult $result): bool => \array_intersect(array_keys($result->getPayload()), $properties) !== []
        );
    }

    /**
     * @return list<IDStructure>
     */
    public function getPrimaryKeys(): array
    {
        return array_values($this->map(static fn (EntityWriteResult $result): array|string => $result->getPrimaryKey()));
    }

    public function getApiAlias(): string
    {
        return 'dal_entity_write_result_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return EntityWriteResult::class;
    }
}
