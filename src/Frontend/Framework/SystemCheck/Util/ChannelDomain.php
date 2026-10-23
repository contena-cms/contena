<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class ChannelDomain extends Struct
{
    private function __construct(
        public readonly string $channelId,
        public readonly string $url,
        public readonly string $id,
        public readonly string $languageId,
        public readonly string $currencyId,
    ) {
    }

    public static function create(
        string $channelId,
        string $url,
        string $id,
        string $languageId,
        string $currencyId,
    ): self {
        return new self($channelId, $url, $id, $languageId, $currencyId);
    }
}
