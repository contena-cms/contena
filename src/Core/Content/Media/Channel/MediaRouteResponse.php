<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Channel;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<MediaCollection>
 */
class MediaRouteResponse extends ChannelApiResponse
{
    public function getMediaCollection(): MediaCollection
    {
        return $this->object;
    }
}
