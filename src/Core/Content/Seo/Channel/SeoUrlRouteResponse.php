<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Channel;

use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<EntitySearchResult<SeoUrlCollection>>
 */
class SeoUrlRouteResponse extends ChannelApiResponse
{
    public function getSeoUrls(): SeoUrlCollection
    {
        return $this->object->getEntities();
    }
}
