<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractCookieConsentLogRoute
{
    abstract public function getDecorated(): AbstractCookieConsentLogRoute;

    abstract public function log(Request $request, ChannelContext $channelContext): NoContentResponse;
}
