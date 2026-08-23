<?php declare(strict_types=1);

namespace Contena\Core\System\Region;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<RegionEntity>
 */
class RegionCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(static fn (RegionEntity $region) => $region->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(static fn (RegionEntity $region) => $region->getCountryId() === $id);
    }

    public function filterByParentId(?string $id): self
    {
        return $this->filter(static fn (RegionEntity $region) => $region->getParentId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'region_collection';
    }

    protected function getExpectedClass(): string
    {
        return RegionEntity::class;
    }
}
