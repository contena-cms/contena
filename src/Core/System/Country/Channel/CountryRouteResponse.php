<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Country\CountryCollection;

/**
 * @extends ChannelApiResponse<EntitySearchResult<CountryCollection>>
 */
class CountryRouteResponse extends ChannelApiResponse
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
