<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

abstract class AbstractImitateUserRoute
{
    abstract public function getDecorated(): AbstractImitateUserRoute;

    abstract public function imitateUserLogin(RequestDataBag $data, ChannelContext $context): ContextTokenResponse;
}
