<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Footer;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface FooterPageletLoaderInterface
{
    public function load(Request $request, ChannelContext $channelContext): FooterPagelet;
}
