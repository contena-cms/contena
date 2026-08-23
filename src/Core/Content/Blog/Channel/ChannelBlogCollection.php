<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\BlogCollection;

/**
 * @extends BlogCollection<ChannelBlogEntity>
 */
class ChannelBlogCollection extends BlogCollection
{
    public function getApiAlias(): string
    {
        return 'channel_blog_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelBlogEntity::class;
    }
}
