<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Member\MemberEntity;

/**
 * This route can be used to delete a member
 */
abstract class AbstractDeleteMemberRoute
{
    abstract public function getDecorated(): AbstractDeleteMemberRoute;

    abstract public function delete(ChannelContext $context, MemberEntity $member): NoContentResponse;
}
