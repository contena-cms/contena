<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;

/**
 * This route is used get the MemberRecoveryIsExpiredResponse entry for a given hash
 * The required parameter is: "hash"
 */
abstract class AbstractMemberRecoveryIsExpiredRoute
{
    abstract public function getDecorated(): AbstractMemberRecoveryIsExpiredRoute;

    abstract public function load(RequestDataBag $data, ChannelContext $context): MemberRecoveryIsExpiredResponse;
}
