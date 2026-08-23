<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Member\MemberEntity;

/**
 * @extends ChannelApiResponse<MemberEntity>
 */
class MemberResponse extends ChannelApiResponse
{
    public function getMember(): MemberEntity
    {
        return $this->object;
    }
}
