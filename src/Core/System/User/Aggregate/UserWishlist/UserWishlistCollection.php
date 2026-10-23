<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserWishlist;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserWishlistEntity>
 *
 * @codeCoverageIgnore
 */
class UserWishlistCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_wishlist_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserWishlistEntity::class;
    }
}
