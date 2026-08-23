<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class ChannelDomain extends Struct
{
    private function __construct(
        public readonly string $channelId,
        public readonly string $url,
    ) {
    }

    public static function create(string $channelId, string $url): self
    {
        return new self($channelId, $url);
    }
}
