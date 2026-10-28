<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Exception;

use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class DuplicateWishlistProductException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::DUPLICATE_WISHLIST_PRODUCT,
            'Product already added in wishlist'
        );
    }
}
