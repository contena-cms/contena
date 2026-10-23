<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\UpsertAddressRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<UpsertAddressRouteResponse>
 */
final class UpsertAddressRouteExtension extends Extension
{
    public const NAME = 'upsert-address-route.upsert';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly ?string $addressId,
        public readonly RequestDataBag $data,
        public readonly ChannelContext $context,
        public readonly UserEntity $user,
    ) {
    }
}
