<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBlogCommentRoute
{
    abstract public function getDecorated(): AbstractBlogCommentRoute;

    abstract public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogCommentRouteResponse;
}
