<?php declare(strict_types=1);

namespace Contena\Core\System\User\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\System\User\UserException;

class UserWishlistBlogExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.user_wishlist.channel_id__customer_id\'/', $e->getMessage())) {
            return UserException::duplicateWishlistBlog();
        }

        return null;
    }
}
