<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation\Error;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface ErrorPageLoaderInterface
{
    public function load(string $landingPageId, Request $request, ChannelContext $context): ErrorPage;
}
