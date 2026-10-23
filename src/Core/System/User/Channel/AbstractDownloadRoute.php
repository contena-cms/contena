<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractDownloadRoute
{
    abstract public function getDecorated(): AbstractDownloadRoute;

    abstract public function load(Request $request, ChannelContext $context): Response;
}
