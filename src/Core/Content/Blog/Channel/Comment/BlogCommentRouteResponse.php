<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<EntitySearchResult<BlogCommentCollection>>
 */
class BlogCommentRouteResponse extends ChannelApiResponse
{
    /**
     * @return EntitySearchResult<BlogCommentCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->object;
    }
}
