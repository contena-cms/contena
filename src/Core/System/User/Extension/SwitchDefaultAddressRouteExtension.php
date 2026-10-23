<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\User\UserEntity;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
final class SwitchDefaultAddressRouteExtension extends Extension
{
    public const NAME = 'switch-default-address-route.swap';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $addressId,
        public readonly string $type,
        public readonly ChannelContext $context,
        public readonly UserEntity $user,
    ) {
    }
}
