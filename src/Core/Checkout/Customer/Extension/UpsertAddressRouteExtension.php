<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Extension;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\SalesChannel\UpsertAddressRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<UpsertAddressRouteResponse>
 */
#[Package('checkout')]
final class UpsertAddressRouteExtension extends Extension
{
    public const NAME = 'upsert-address-route.upsert';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly ?string $addressId,
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
