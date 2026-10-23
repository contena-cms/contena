<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

/**
 * This route can be used to logout the current context token
 */
abstract class AbstractLogoutRoute
{
    abstract public function getDecorated(): AbstractLogoutRoute;

    abstract public function logout(ChannelContext $context, RequestDataBag $data): ContextTokenResponse;
}
