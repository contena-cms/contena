<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Telemetry;

use Contena\Core\Defaults;

/**
 * Maps a channel type id to a small, bounded label value, so the (plugin-extensible) set of
 * channel types does not blow up the metric label cardinality.
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric labels may use
 * `policy: open`. Known outputs: web, api, other.
 *
 * @internal
 *
 * @final
 */
class ChannelTypeResolver
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        Defaults::CHANNEL_TYPE_WEB => 'web',
        Defaults::CHANNEL_TYPE_API => 'api',
    ];

    public function resolve(string $typeId): string
    {
        return self::TYPES[$typeId] ?? 'other';
    }
}
