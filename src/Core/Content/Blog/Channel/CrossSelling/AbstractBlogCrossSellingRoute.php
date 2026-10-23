<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\CrossSelling;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route will be used to load all cross-selling lists of the provided blog id
 */
abstract class AbstractBlogCrossSellingRoute
{
    abstract public function getDecorated(): AbstractBlogCrossSellingRoute;

    abstract public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogCrossSellingRouteResponse;
}
