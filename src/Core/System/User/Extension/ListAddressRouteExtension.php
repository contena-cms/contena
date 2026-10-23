<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Channel\ListAddressRouteResponse;
use Contena\Core\System\User\UserEntity;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ListAddressRouteResponse>
 */
final class ListAddressRouteExtension extends Extension
{
    public const NAME = 'list-address-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Criteria $criteria,
        public readonly ChannelContext $context,
        public readonly UserEntity $user,
    ) {
    }
}
