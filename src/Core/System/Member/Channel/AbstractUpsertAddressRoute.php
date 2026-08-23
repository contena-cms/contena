<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;

/**
 * This route can be used to create or update new member addresses
 */
abstract class AbstractUpsertAddressRoute
{
    abstract public function upsert(?string $addressId, RequestDataBag $data, ChannelContext $context, MemberEntity $member): UpsertAddressRouteResponse;

    abstract public function getDecorated(): AbstractUpsertAddressRoute;
}
