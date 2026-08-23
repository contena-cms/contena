<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberAddress;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Region\RegionCollection;

/**
 * @extends EntityCollection<MemberAddressEntity>
 */
class MemberAddressCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getMemberIds(): array
    {
        return $this->fmap(static fn (MemberAddressEntity $address) => $address->getMemberId());
    }

    public function filterByMemberId(string $id): self
    {
        return $this->filter(static fn (MemberAddressEntity $address) => $address->getMemberId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(static fn (MemberAddressEntity $address) => $address->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(static fn (MemberAddressEntity $address) => $address->getCountryId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getRegionIds(): array
    {
        return $this->fmap(static fn (MemberAddressEntity $address) => $address->getRegionId());
    }

    public function filterByRegionId(string $id): self
    {
        return $this->filter(static fn (MemberAddressEntity $address) => $address->getRegionId() === $id);
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection($this->fmap(static fn (MemberAddressEntity $address) => $address->getCountry()));
    }

    public function getRegions(): RegionCollection
    {
        return new RegionCollection($this->fmap(static fn (MemberAddressEntity $address) => $address->getRegion()));
    }

    public function getApiAlias(): string
    {
        return 'member_address_collection';
    }

    protected function getExpectedClass(): string
    {
        return MemberAddressEntity::class;
    }
}
