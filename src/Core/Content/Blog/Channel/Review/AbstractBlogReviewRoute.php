<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBlogReviewRoute
{
    abstract public function getDecorated(): AbstractBlogReviewRoute;

    abstract public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogReviewRouteResponse;
}
