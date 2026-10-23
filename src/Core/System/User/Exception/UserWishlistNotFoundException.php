<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UserWishlistNotFoundException extends UserException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::WISHLIST_NOT_FOUND,
            'Wishlist for this user was not found.'
        );
    }
}
