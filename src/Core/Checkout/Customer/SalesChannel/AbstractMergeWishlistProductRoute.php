<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SuccessResponse;

/**
 * This route can be used to merge wishlist products from guest users to registered users.
 */
#[Package('checkout')]
abstract class AbstractMergeWishlistProductRoute
{
    abstract public function getDecorated(): AbstractMergeWishlistProductRoute;

    abstract public function merge(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse;
}
