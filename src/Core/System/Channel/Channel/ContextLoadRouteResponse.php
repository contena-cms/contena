<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ChannelContext>
 */
class ContextLoadRouteResponse extends StoreApiResponse
{
    public function getContext(): ChannelContext
    {
        return $this->object;
    }
}
