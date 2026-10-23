<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Country;

use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Frontend\Pagelet\Pagelet;

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
