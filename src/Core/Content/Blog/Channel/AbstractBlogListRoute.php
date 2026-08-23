<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;

/**
 * This route is a general route to get blogs of the channel.
 */
abstract class AbstractBlogListRoute
{
    abstract public function getDecorated(): AbstractBlogListRoute;

    abstract public function load(Criteria $criteria, ChannelContext $context): BlogListResponse;
}
