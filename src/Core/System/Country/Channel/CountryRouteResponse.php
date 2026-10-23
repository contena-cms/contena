<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\Country\CountryCollection;

/**
 * @extends StoreApiResponse<EntitySearchResult<CountryCollection>>
 */
class CountryRouteResponse extends StoreApiResponse
{
    /**
     * @return EntitySearchResult<CountryCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->object;
    }

    public function getCountries(): CountryCollection
    {
        return $this->object->getEntities();
    }
}
