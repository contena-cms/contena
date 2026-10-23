<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\System\User\Channel\UserGroupRegistrationSettingsRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<UserGroupRegistrationSettingsRouteResponse>
 */
final class UserGroupRegistrationSettingsRouteExtension extends Extension
{
    public const NAME = 'user-group-registration-settings-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $userGroupId,
        public readonly ChannelContext $context,
    ) {
    }
}
