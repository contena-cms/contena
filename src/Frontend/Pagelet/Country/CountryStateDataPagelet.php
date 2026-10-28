<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Country;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Storefront\Pagelet\Pagelet;

#[Package('discovery')]
class CountryStateDataPagelet extends Pagelet
{
    protected CountryStateCollection $states;

    public function __construct()
    {
        $this->states = new CountryStateCollection();
    }

    public function getStates(): CountryStateCollection
    {
        return $this->states;
    }

    public function setStates(CountryStateCollection $states): void
    {
        $this->states = $states;
    }
}
