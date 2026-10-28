<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Extension;

use Contena\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CustomerGroupRegistrationSettingsRouteResponse>
 */
#[Package('checkout')]
final class CustomerGroupRegistrationSettingsRouteExtension extends Extension
{
    public const NAME = 'customer-group-registration-settings-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $customerGroupId,
        public readonly SalesChannelContext $context,
    ) {
    }
}
