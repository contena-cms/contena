<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Detail;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Frontend\Page\Page;

class AddressDetailPage extends Page
{
    protected ?UserAddressEntity $address = null;

    protected SalutationCollection $salutations;

    protected CountryCollection $countries;

    public function getAddress(): ?UserAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?UserAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }
}
