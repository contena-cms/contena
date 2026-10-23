<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;

/**
 * @internal
 */
enum ApiCallerType
{
    case AdminUser;
    case Integration;

    /**
     * An integration always acts as itself, never as the admin user it names in the `sw-app-admin-user-id` header.
     * Returns null when the source is not an Admin API caller.
     *
     * @return ($source is AdminApiSource ? self : null)
     */
    public static function of(ContextSource $source): ?self
    {
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        return $source->getIntegrationId() !== null ? self::Integration : self::AdminUser;
    }

    public function idOf(ContextSource $source): ?string
    {
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        return match ($this) {
            self::Integration => $source->getIntegrationId(),
            self::AdminUser => $source->getUserId(),
        };
    }
}
