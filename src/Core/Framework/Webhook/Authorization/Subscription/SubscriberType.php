<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Authorization\Subscription;

use Contena\Core\Framework\Api\Acl\ApiCallerType;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
enum SubscriberType
{
    case App;
    case AdminUser;
    case Integration;
    case None;

    public static function of(ApiCallerType $caller): self
    {
        return match ($caller) {
            ApiCallerType::AdminUser => self::AdminUser,
            ApiCallerType::Integration => self::Integration,
        };
    }
}
