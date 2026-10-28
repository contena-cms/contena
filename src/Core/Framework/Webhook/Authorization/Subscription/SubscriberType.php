<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Authorization\Subscription;

use Contena\Core\Framework\Api\Acl\ApiCaller;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum SubscriberType
{
    case App;
    case User;
    case Integration;
    case None;

    public static function of(ApiCaller $caller): self
    {
        return match ($caller->type) {
            ApiCaller::USER => self::User,
            ApiCaller::INTEGRATION => self::Integration,
        };
    }
}
