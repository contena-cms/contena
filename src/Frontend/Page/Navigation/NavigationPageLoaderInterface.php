<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface NavigationPageLoaderInterface
{
    public function load(Request $request, ChannelContext $context): NavigationPage;
}
