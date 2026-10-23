<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Garan;

use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractGaranLabelRoute
{
    abstract public function getDecorated(): AbstractGaranLabelRoute;

    abstract public function load(string $blogId, ChannelContext $context): GaranLabelRouteResponse;
}
