<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<ChannelBlogEntity>
 */
class BlogDetailRouteResponse extends ChannelApiResponse
{
    public function __construct(ChannelBlogEntity $blog)
    {
        parent::__construct($blog);
    }

    public function getBlog(): ChannelBlogEntity
    {
        return $this->object;
    }
}
