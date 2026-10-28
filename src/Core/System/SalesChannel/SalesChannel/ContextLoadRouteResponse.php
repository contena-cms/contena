<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SalesChannelContext>
 */
#[Package('framework')]
class ContextLoadRouteResponse extends StoreApiResponse
{
    public function getContext(): SalesChannelContext
    {
        return $this->object;
    }
}
