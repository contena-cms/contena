<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns content layout data in the configured output format.
 */
abstract class AbstractContentRoute
{
    abstract public function getDecorated(): AbstractContentRoute;

    abstract public function load(string $path, Request $request, ChannelContext $context): AbstractContentRouteResponse;
}
