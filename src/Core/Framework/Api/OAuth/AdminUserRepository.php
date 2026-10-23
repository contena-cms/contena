<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Contena\Core\Framework\Api\OAuth\AdminUser\AdminUser;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * OAuth integrations should rely on {@see UserRepositoryInterface} instead of this concrete Contena class.
 */
#[BecomesInternal(version: 'v6.8.0')]
class AdminUserRepository implements UserRepositoryInterface
{
    /**
     * Bcrypt hash for a static placeholder password used to equalize timing when no admin user is found.
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

    public function getUserEntityByUserCredentials(
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        if ($this->loginConfigService->getConfig()?->useDefault === false) {
            // never allow login via password if the default login is disabled (e.g. using SSO only)
            return null;
        }

        $builder = $this->connection->createQueryBuilder();
        $adminUser = $builder->select('admin_user.id', 'admin_user.password', 'admin_user.active')
            ->from('admin_user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->fetchAssociative();

        if (!$adminUser) {
            // Prevent admin user enumeration via timing attacks by always running password_verify().
            $adminUser = ['password' => self::PLACEHOLDER_PASSWORD_HASH];
            $password = 'invalid-password-will-always-fail';
        }

        if (!password_verify($password, (string) $adminUser['password'])) {
            return null;
        }

        if (!(bool) $adminUser['active']) {
            return null;
        }

        return new AdminUser(Uuid::fromBytesToHex($adminUser['id']));
    }
}
