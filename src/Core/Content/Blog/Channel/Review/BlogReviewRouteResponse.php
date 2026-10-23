<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<BlogReviewCollection>>
 */
class BlogReviewRouteResponse extends StoreApiResponse
{
    /**
     * @return EntitySearchResult<BlogReviewCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->object;
    }
}
