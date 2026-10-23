<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\PurchaseLimit;

use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends ChannelApiResponse<BlogPurchaseLimitCollection>
 */
class BlogPurchaseLimitRouteResponse extends ChannelApiResponse
{
    public function getResult(): BlogPurchaseLimitCollection
    {
        return $this->object;
    }
}
