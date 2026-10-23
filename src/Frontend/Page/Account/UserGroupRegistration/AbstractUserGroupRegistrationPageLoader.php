<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\UserGroupRegistration;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads the user group registration page
 */
abstract class AbstractUserGroupRegistrationPageLoader
{
    abstract public function getDecorated(): AbstractUserGroupRegistrationPageLoader;

    abstract public function load(Request $request, ChannelContext $channelContext): UserGroupRegistrationPage;
}
