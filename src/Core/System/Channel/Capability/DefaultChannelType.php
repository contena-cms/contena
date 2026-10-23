<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Capability;

use Contena\Core\Defaults;

/**
 * Misses the types extensions register; consumers go through `ChannelTypeCapabilityRegistry`.
 *
 * @internal
 */
enum DefaultChannelType: string
{
    case FRONTEND = Defaults::CHANNEL_TYPE_FRONTEND;
    case HEADLESS = Defaults::CHANNEL_TYPE_API;
    case BLOG_COMPARISON = Defaults::CHANNEL_TYPE_BLOG_COMPARISON;

    public function isTransactional(): bool
    {
        return match ($this) {
            self::FRONTEND, self::HEADLESS => true,
            self::BLOG_COMPARISON => false,
        };
    }
}
