<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route loads a single category for the authenticated channel.
 * It is also possible to use "home" as navigationId to load the start page.
 */
abstract class AbstractCategoryRoute
{
    abstract public function getDecorated(): AbstractCategoryRoute;

    abstract public function load(string $navigationId, Request $request, ChannelContext $context): CategoryRouteResponse;
}
