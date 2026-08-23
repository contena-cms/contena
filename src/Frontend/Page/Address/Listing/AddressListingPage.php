<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Listing;

use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressCollection;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Frontend\Page\Page;

class AddressListingPage extends Page
{
    protected MemberAddressCollection $addresses;

    protected CountryCollection $countries;

    protected ?MemberAddressEntity $address = null;

    public function getAddresses(): MemberAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(MemberAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getAddress(): ?MemberAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?MemberAddressEntity $address): void
    {
        $this->address = $address;
    }
}
