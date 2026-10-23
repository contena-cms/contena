<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\Channel;

use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<BreadcrumbCollection>
 */
class BreadcrumbRouteResponse extends ChannelApiResponse
{
    public function getBreadcrumbCollection(): BreadcrumbCollection
    {
        return $this->object;
    }
}
