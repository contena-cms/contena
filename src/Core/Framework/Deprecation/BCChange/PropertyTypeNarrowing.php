<?php declare(strict_types=1);

namespace Contena\Core\Framework\Deprecation\BCChange;


/**
 * @internal
 *
 * Signals that a property's type will be narrowed in the given version.
 *
 * Assignments that are not covered by the announced type must be adjusted before the change
 * happens. Reading the property remains compatible because every future value is still covered
 * by the current type.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PropertyTypeNarrowing implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly string $newType,
        public readonly ?string $description = null,
    ) {
    }
}
