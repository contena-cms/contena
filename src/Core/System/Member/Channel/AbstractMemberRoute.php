<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to get information about the current logged-in member
 */
abstract class AbstractMemberRoute
{
    abstract public function getDecorated(): AbstractMemberRoute;

    abstract public function load(Request $request, ChannelContext $context, Criteria $criteria, MemberEntity $member): MemberResponse;
}
