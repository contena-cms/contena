<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Region;

use Contena\Core\System\Region\RegionCollection;
use Contena\Frontend\Pagelet\Pagelet;

class RegionDataPagelet extends Pagelet
{
    protected RegionCollection $regions;

    public function __construct()
    {
        $this->regions = new RegionCollection();
    }

    public function getRegions(): RegionCollection
    {
        return $this->regions;
    }

    public function setRegions(RegionCollection $regions): void
    {
        $this->regions = $regions;
    }
}
