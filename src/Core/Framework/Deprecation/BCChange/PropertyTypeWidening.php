<?php declare(strict_types=1);

namespace Contena\Core\Framework\Deprecation\BCChange;


/**
 * @internal
 *
 * Signals that a property's type will be widened in the given version.
 *
 * Reads must be prepared to handle every value of the announced type before the change happens.
 * Assignments are not affected because every currently accepted value remains accepted.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PropertyTypeWidening implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly string $newType,
        public readonly ?string $description = null,
    ) {
    }
}
