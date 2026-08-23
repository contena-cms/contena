<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Telemetry;

/**
 * Buckets a flow trigger event name ({@see \Contena\Core\Content\Flow\Dispatching\StorableFlow::getName()},
 * e.g. `user.recovery.request`, `state_enter.example.state.active`) into a small, bounded group set,
 * so the open set of business/plugin event names does not blow up the metric label cardinality.
 *
 * Ordered prefix match, first hit wins; unmatched names (including plugin events) fall through to `other`.
 * Owns its bounded output set, so the consuming metric label may use `policy: open`. Known outputs:
 * state-change, member, user, channel, content, other.
 *
 * Prefix resolver memoizes per event name (workers are long-lived, each distinct name resolves once
 * per process). The hardcoded map is intentional - see the rationale on
 * {@see \Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 */
class TriggerGroupResolver
{
    /**
     * Ordered prefix to group; more specific prefixes first.
     *
     * @var array<string, string>
     */
    private const array PREFIXES = [
        'state_enter.' => 'state-change',
        'state_leave.' => 'state-change',
        'member.' => 'member',
        'user.' => 'user',
        'channel.' => 'channel',
        'blog.' => 'content',
        'category.' => 'content',
        'landing_page.' => 'content',
    ];

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function resolve(string $eventName): string
    {
        return $this->cache[$eventName] ??= $this->resolveUncached($eventName);
    }

    private function resolveUncached(string $eventName): string
    {
        foreach (self::PREFIXES as $prefix => $group) {
            if (str_starts_with($eventName, $prefix)) {
                return $group;
            }
        }

        return 'other';
    }
}
