<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\PurchaseLimit;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<BlogPurchaseLimitCollection>
 */
class BlogPurchaseLimitRouteResponse extends StoreApiResponse
{
    public function getResult(): BlogPurchaseLimitCollection
    {
        return $this->object;
    }
}
