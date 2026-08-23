<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

abstract class AbstractImitateMemberRoute
{
    abstract public function getDecorated(): AbstractImitateMemberRoute;

    abstract public function imitateMemberLogin(RequestDataBag $data, ChannelContext $context): ContextTokenResponse;
}
