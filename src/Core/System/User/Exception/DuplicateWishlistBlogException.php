<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class DuplicateWishlistBlogException extends UserException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::DUPLICATE_WISHLIST_BLOG,
            'Blog already added in wishlist'
        );
    }
}
