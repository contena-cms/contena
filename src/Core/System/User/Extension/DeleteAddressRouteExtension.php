<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
final class DeleteAddressRouteExtension extends Extension
{
    public const NAME = 'delete-address-route.delete';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $addressId,
        public readonly ChannelContext $context,
        public readonly UserEntity $user,
    ) {
    }
}
