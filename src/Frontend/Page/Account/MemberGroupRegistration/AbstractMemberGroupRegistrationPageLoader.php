<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\MemberGroupRegistration;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads the member group registration page.
 */
abstract class AbstractMemberGroupRegistrationPageLoader
{
    abstract public function getDecorated(): AbstractMemberGroupRegistrationPageLoader;

    abstract public function load(Request $request, ChannelContext $channelContext): MemberGroupRegistrationPage;
}
