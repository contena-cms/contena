<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<BlogCollection>>
 */
class BlogListResponse extends StoreApiResponse
{
    public function getBlogs(): BlogCollection
    {
        return $this->object->getEntities();
    }
}
