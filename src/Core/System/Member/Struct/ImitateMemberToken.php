<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Struct;

use Contena\Core\Framework\JWT\Struct\JWTStruct;

/**
 * @codeCoverageIgnore
 */
class ImitateMemberToken extends JWTStruct
{
    public string $channelId;

    public string $memberId;
}
