<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Suggest;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used for the blog suggest in the page header
 */
abstract class AbstractBlogSuggestRoute
{
    abstract public function getDecorated(): AbstractBlogSuggestRoute;

    abstract public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSuggestRouteResponse;
}
