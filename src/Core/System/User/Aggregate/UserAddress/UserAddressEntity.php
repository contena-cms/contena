<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserAddress;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class UserAddressEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $userId;

    protected string $countryId;

    protected ?string $countryStateId = null;

    protected string $name;


    protected ?string $zipcode = null;

    protected string $city;

    protected ?string $company = null;

    protected ?string $department = null;

    protected ?string $title = null;

    protected string $street;

    protected ?string $phoneNumber = null;

    protected ?string $additionalAddressLine1 = null;

    protected ?string $additionalAddressLine2 = null;

    protected ?CountryEntity $country = null;

    protected ?CountryStateEntity $countryState = null;


    protected ?UserEntity $user = null;

    protected ?UserEntity $defaultBillingAddressUser = null;

    protected ?UserEntity $defaultShippingAddressUser = null;

    protected ?string $hash = null;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getCountryStateId(): ?string
    {
        return $this->countryStateId;
    }

    public function setCountryStateId(string $countryStateId): void
    {
        $this->countryStateId = $countryStateId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(string $department): void
    {
        $this->department = $department;
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

    public function getCountryState(): ?CountryStateEntity
    {
        return $this->countryState;
    }

    public function setCountryState(CountryStateEntity $countryState): void
    {
        $this->countryState = $countryState;
    }



    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getDefaultBillingAddressUser(): ?UserEntity
    {
        return $this->defaultBillingAddressUser;
    }

    public function setDefaultBillingAddressUser(?UserEntity $defaultBillingAddressUser): void
    {
        $this->defaultBillingAddressUser = $defaultBillingAddressUser;
    }

    public function getDefaultShippingAddressUser(): ?UserEntity
    {
        return $this->defaultShippingAddressUser;
    }

    public function setDefaultShippingAddressUser(?UserEntity $defaultShippingAddressUser): void
    {
        $this->defaultShippingAddressUser = $defaultShippingAddressUser;
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
