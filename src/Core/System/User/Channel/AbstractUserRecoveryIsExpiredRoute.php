<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;

/**
 * This route is used get the UserRecoveryIsExpiredResponse entry for a given hash
 * The required parameter is: "hash"
 */
abstract class AbstractUserRecoveryIsExpiredRoute
{
    abstract public function getDecorated(): AbstractUserRecoveryIsExpiredRoute;

    abstract public function load(RequestDataBag $data, ChannelContext $context): UserRecoveryIsExpiredResponse;
}
