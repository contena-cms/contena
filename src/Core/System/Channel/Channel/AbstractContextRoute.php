<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\System\Channel\ChannelContext;

/**
 * This route can be used to fetch the current context.
 *
 * The context contains information about the logged-in member, selected language and channel.
 */
abstract class AbstractContextRoute
{
    abstract public function getDecorated(): AbstractContextRoute;

    abstract public function load(ChannelContext $context): ContextLoadRouteResponse;
}
