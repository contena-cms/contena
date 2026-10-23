<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\BlogCollection;

/**
 * @extends BlogCollection<ChannelBlogEntity>
 *
 * @codeCoverageIgnore
 */
class ChannelBlogCollection extends BlogCollection
{
    public function getApiAlias(): string
    {
        return 'channel_product_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelBlogEntity::class;
    }
}
