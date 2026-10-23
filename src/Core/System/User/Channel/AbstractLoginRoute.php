<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

/**
 * This route is used to login and get a new context token
 * The required parameters are "email" and "password"
 */
abstract class AbstractLoginRoute
{
    abstract public function getDecorated(): AbstractLoginRoute;

    abstract public function login(RequestDataBag $data, ChannelContext $context): ContextTokenResponse;
}
