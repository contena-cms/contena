<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;

/**
 * @extends ChannelApiResponse<EntitySearchResult<CountryStateCollection>>
 */
class CountryStateRouteResponse extends ChannelApiResponse
{
    public function getStates(): CountryStateCollection
    {
        return $this->object->getEntities();
    }
}
