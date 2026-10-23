<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Menu\Offcanvas;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface MenuOffcanvasPageletLoaderInterface
{
    public function load(Request $request, ChannelContext $context): MenuOffcanvasPagelet;
}
