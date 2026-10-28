<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SuccessResponse;

#[Package('checkout')]
abstract class AbstractRemoveWishlistProductRoute
{
    abstract public function getDecorated(): AbstractRemoveWishlistProductRoute;

    abstract public function delete(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse;
}
