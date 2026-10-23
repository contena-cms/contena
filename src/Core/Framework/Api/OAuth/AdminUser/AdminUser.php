<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth\AdminUser;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;

/**
 * OAuth integrations should rely on {@see UserEntityInterface} instead of this concrete Contena class.
 */
#[BecomesInternal(version: 'v6.8.0')]
class AdminUser implements UserEntityInterface
{
    /**
     * @param non-empty-string $adminUserId
     */
    public function __construct(private readonly string $adminUserId)
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
