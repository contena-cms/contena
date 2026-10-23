<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Channel;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<MediaCollection>
 */
class MediaRouteResponse extends StoreApiResponse
{
    public function getMediaCollection(): MediaCollection
    {
        return $this->object;
    }
}
