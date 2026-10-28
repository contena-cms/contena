<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Aggregate\CustomerWishlist;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerWishlistEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CustomerWishlistCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_wishlist_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerWishlistEntity::class;
    }
}
