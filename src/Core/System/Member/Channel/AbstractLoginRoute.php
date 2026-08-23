<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

abstract class AbstractLoginRoute
{
    abstract public function getDecorated(): AbstractLoginRoute;

    abstract public function login(#[\SensitiveParameter] RequestDataBag $data, ChannelContext $context): ContextTokenResponse;
}
