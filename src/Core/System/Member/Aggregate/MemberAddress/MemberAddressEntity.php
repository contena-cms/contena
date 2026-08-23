<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberAddress;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Region\RegionEntity;

class MemberAddressEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $memberId;

    protected string $countryId;

    protected ?string $regionId = null;

    protected string $firstName;

    protected string $lastName;

    protected ?string $zipcode = null;

    protected string $city;

    protected ?string $title = null;

    protected string $street;

    protected ?string $phoneNumber = null;

    protected ?string $additionalAddressLine1 = null;

    protected ?string $additionalAddressLine2 = null;

    protected ?CountryEntity $country = null;

    protected ?RegionEntity $region = null;

    protected ?MemberEntity $member = null;

    protected ?string $hash = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getMemberId(): string
    {
        return $this->memberId;
    }

    public function setMemberId(string $memberId): void
    {
        $this->memberId = $memberId;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getRegionId(): ?string
    {
        return $this->regionId;
    }

    public function setRegionId(string $regionId): void
    {
        $this->regionId = $regionId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function setZipcode(?string $zipcode): void
    {
        $this->zipcode = ($zipcode === null || $zipcode === '') ? null : $zipcode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getAdditionalAddressLine1(): ?string
    {
        return $this->additionalAddressLine1;
    }

    public function setAdditionalAddressLine1(string $additionalAddressLine1): void
    {
        $this->additionalAddressLine1 = $additionalAddressLine1;
    }

    public function getAdditionalAddressLine2(): ?string
    {
        return $this->additionalAddressLine2;
    }

    public function setAdditionalAddressLine2(string $additionalAddressLine2): void
    {
        $this->additionalAddressLine2 = $additionalAddressLine2;
    }

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(CountryEntity $country): void
    {
        $this->country = $country;
    }

    public function getRegion(): ?RegionEntity
    {
        return $this->region;
    }

    public function setRegion(RegionEntity $region): void
    {
        $this->region = $region;
    }

    public function getMember(): ?MemberEntity
    {
        return $this->member;
    }

    public function setMember(MemberEntity $member): void
    {
        $this->member = $member;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }
}
