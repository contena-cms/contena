<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ExtensionStruct>
 */
class ExtensionCollection extends Collection
{
    private int $total = 0;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function merge(self $collection): self
    {
        foreach ($collection as $entity) {
            if ($this->has($entity->getName())) {
                continue;
            }
            $this->set($entity->getName(), $entity);
        }

        return $this;
    }

    public function filterByType(string $type): self
    {
        return $this->filter(static fn (ExtensionStruct $ext) => $ext->getType() === $type);
    }

    protected function getExpectedClass(): ?string
    {
        return ExtensionStruct::class;
    }
}
