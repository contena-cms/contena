<?php declare(strict_types=1);

namespace Contena\Core\System\Language\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Language\LanguageCollection;

/**
 * @extends ChannelApiResponse<EntitySearchResult<LanguageCollection>>
 */
class LanguageRouteResponse extends ChannelApiResponse
{
    public function getLanguages(): LanguageCollection
    {
        return $this->object->getEntities();
    }
}
