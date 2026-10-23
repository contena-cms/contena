<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\FindVariant;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used the search for a matching blog variant by given options
 */
abstract class AbstractFindBlogVariantRoute
{
    abstract public function getDecorated(): AbstractFindBlogVariantRoute;

    abstract public function load(string $blogId, Request $request, ChannelContext $context): FindBlogVariantRouteResponse;
}
