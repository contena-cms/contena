<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Search;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used for the blog search in the search pages
 */
abstract class AbstractBlogSearchRoute
{
    abstract public function getDecorated(): AbstractBlogSearchRoute;

    abstract public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSearchRouteResponse;
}
