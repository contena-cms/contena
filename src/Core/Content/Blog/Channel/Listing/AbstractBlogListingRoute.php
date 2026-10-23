<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used for the blog listing in the cms pages
 */
abstract class AbstractBlogListingRoute
{
    abstract public function getDecorated(): AbstractBlogListingRoute;

    abstract public function load(string $categoryId, Request $request, ChannelContext $context, Criteria $criteria): BlogListingRouteResponse;
}
