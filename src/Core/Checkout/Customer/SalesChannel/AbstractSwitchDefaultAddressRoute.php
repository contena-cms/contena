<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\NoContentResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be to switch the current default shipping or billing address
 */
#[Package('checkout')]
abstract class AbstractSwitchDefaultAddressRoute
{
    final public const TYPE_BILLING = 'billing';
    final public const TYPE_SHIPPING = 'shipping';

    abstract public function getDecorated(): AbstractSwitchDefaultAddressRoute;

    abstract public function swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse;
}
