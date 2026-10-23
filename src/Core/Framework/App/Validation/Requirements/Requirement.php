<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Requirements;

use Contena\Core\Framework\App\Manifest\Manifest;

/**
 * @internal
 */
interface Requirement
{
    /**
     * Validates a specific requirement for an app.
     * Returns an UnmetRequirement if validation fails, or null if it passes.
     */
    public function validate(Manifest $manifest): ?UnmetRequirement;

    /**
     * Returns the name of the requirement this validator handles
     */
    public static function name(): string;

    /**
     * Checks if this validator applies to the given manifest
     */
    public function required(Manifest $manifest): bool;
}
