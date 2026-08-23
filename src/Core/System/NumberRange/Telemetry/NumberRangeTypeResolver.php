<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Telemetry;

/**
 * Buckets a number-range type technical name into a small, bounded set of groups, so plugin-defined
 * types don't blow up the metric label cardinality.
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric label may use
 * `policy: open`. Known outputs: member, user, other.
 *
 * The hardcoded map is intentional - see the rationale on
 * {@see \Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 */
class NumberRangeTypeResolver
{
    /**
     * Core type technical names (defined in the basic-data migration) -> group.
     *
     * @var array<string, string>
     */
    private const array TYPES = [
        'member' => 'member',
        'user' => 'user',
    ];

    public function resolve(?string $technicalName): string
    {
        if ($technicalName === null) {
            return 'other';
        }

        return self::TYPES[$technicalName] ?? 'other';
    }
}
