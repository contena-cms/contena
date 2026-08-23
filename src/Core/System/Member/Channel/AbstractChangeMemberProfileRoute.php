<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\MemberEntity;

/**
 * This route can be used to change profile information about the logged-in user
 * The required field is "name".
 */
abstract class AbstractChangeMemberProfileRoute
{
    abstract public function getDecorated(): AbstractChangeMemberProfileRoute;

    abstract public function change(RequestDataBag $data, ChannelContext $context, MemberEntity $member): SuccessResponse;
}
