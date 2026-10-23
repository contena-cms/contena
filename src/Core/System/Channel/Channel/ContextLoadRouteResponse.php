<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<ChannelContext>
 */
class ContextLoadRouteResponse extends ChannelApiResponse
{
    public function getContext(): ChannelContext
    {
        return $this->object;
    }
}
