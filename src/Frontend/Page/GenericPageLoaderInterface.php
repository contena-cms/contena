<?php declare(strict_types=1);

namespace Contena\Frontend\Page;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface GenericPageLoaderInterface
{
    public function load(Request $request, ChannelContext $context): Page;
}
