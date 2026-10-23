<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopId;

use Contena\Core\Framework\App\AppException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class ShopIdChangedEvent extends Event
{
    public function __construct(
        public readonly ShopId $newShopId,
        public readonly ?ShopId $oldShopId
    ) {
        if ($oldShopId !== null && $oldShopId->id === $newShopId->id) {
            throw AppException::invalidArgument('ShopIdChangedEvent requires a changed shop id');
        }
    }
}
