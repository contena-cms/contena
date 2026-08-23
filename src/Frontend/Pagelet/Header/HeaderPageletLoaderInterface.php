<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Header;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface HeaderPageletLoaderInterface
{
    public function load(Request $request, ChannelContext $context): HeaderPagelet;
}
