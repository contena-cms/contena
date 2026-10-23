<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;

/**
 * @extends StoreApiResponse<EntitySearchResult<CountryStateCollection>>
 */
class CountryStateRouteResponse extends StoreApiResponse
{
    public function getStates(): CountryStateCollection
    {
        return $this->object->getEntities();
    }
}
