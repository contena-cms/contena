<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Sso\Config\LoginConfigService;

/**
 * OAuth integrations should rely on {@see AdminUserRepositoryInterface} instead of this concrete Contena class.
 */
#[BecomesInternal(version: 'v6.8.0')]
class AdminUserRepository implements AdminUserRepositoryInterface
{
    /**
     * Bcrypt hash for a static placeholder password used to equalize timing when no admin_user is found.
     */
    private const PLACEHOLDER_PASSWORD_HASH = '$2y$12$PVcA5R6ri9kS.7FnFUBRIOLwqU//bCicx5RFxwecAAccbmZ7V7PKu';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly LoginConfigService $loginConfigService,
    ) {
    }

}
