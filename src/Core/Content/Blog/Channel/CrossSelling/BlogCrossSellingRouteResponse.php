<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\CrossSelling;

use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CrossSellingElementCollection>
 */
class BlogCrossSellingRouteResponse extends StoreApiResponse
{
    public function getResult(): CrossSellingElementCollection
    {
        return $this->object;
    }
}
