<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBlogDetailRoute
{
    abstract public function getDecorated(): AbstractBlogDetailRoute;

    abstract public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogDetailRouteResponse;
}
