<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Address\Listing;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryCollection;
use Contena\Storefront\Page\Page;

#[Package('checkout')]
class AddressListingPage extends Page
{
    protected CustomerAddressCollection $addresses;


    protected CountryCollection $countries;


    protected ?CustomerAddressEntity $address = null;

    public function getAddresses(): CustomerAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(CustomerAddressCollection $addresses): void
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



    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }
}
