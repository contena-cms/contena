<?php declare(strict_types=1);

namespace Contena\Core\System\Language\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\Language\LanguageCollection;

/**
 * @extends StoreApiResponse<EntitySearchResult<LanguageCollection>>
 */
class LanguageRouteResponse extends StoreApiResponse
{
    public function getLanguages(): LanguageCollection
    {
        return $this->object->getEntities();
    }
}
