<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Log\Package;

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
