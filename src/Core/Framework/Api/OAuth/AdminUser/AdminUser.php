<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth\AdminUser;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;

/**
 * OAuth integrations should rely on {@see AdminUserEntityInterface} instead of this concrete Contena class.
 */
#[BecomesInternal(version: 'v6.8.0')]
class AdminUser implements AdminUserEntityInterface
{
    /**
     * @param non-empty-string $admin_userId
     */
    public function __construct(private readonly string $admin_userId)
    {
    }

    /**
     * Return the admin_user's identifier.
     *
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->admin_userId;
    }
}
