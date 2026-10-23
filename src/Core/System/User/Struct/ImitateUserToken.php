<?php declare(strict_types=1);

namespace Contena\Core\System\User\Struct;

use Contena\Core\Framework\JWT\Struct\JWTStruct;

/**
 * @codeCoverageIgnore
 */
class ImitateUserToken extends JWTStruct
{
    public string $channelId;

    public string $userId;
}
