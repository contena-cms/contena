<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Telemetry;

use Contena\Core\Defaults;

/**
 * Maps a sales channel type id to a small, bounded label value, so the (plugin-extensible) set of
 * sales channel types does not blow up the metric label cardinality.
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric labels may use
 * `policy: open`. Known outputs: frontend, api, blog_comparison, agentic_commerce, other.
 *
 * Shared resolver — used by the cart calculation and order placed collectors.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
class ChannelTypeResolver
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        Defaults::CHANNEL_TYPE_FRONTEND => 'frontend',
        Defaults::CHANNEL_TYPE_API => 'api',
        Defaults::CHANNEL_TYPE_BLOG_COMPARISON => 'blog_comparison',
        Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE => 'agentic_commerce',
    ];

    public function resolve(string $typeId): string
    {
        return self::TYPES[$typeId] ?? 'other';
    }
}
