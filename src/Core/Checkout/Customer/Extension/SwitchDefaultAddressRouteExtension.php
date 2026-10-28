<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Extension;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\NoContentResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
#[Package('checkout')]
final class SwitchDefaultAddressRouteExtension extends Extension
{
    public const NAME = 'switch-default-address-route.swap';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $addressId,
        public readonly string $type,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
