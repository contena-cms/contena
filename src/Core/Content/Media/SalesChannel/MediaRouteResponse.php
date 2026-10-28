<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\SalesChannel;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<MediaCollection>
 */
#[Package('discovery')]
class MediaRouteResponse extends StoreApiResponse
{
    public function getMediaCollection(): MediaCollection
    {
        return $this->object;
    }
}
