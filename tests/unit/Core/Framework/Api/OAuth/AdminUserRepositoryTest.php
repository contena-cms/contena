<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\OAuth\AdminUserRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(AdminUserRepository::class)]
class AdminUserRepositoryTest extends TestCase
{
    public function testLoginWithDefaultLoginEnabledAndCorrectCredentials(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $admin_user = new AdminUserEntity();
        $admin_user->setId(Uuid::randomBytes());
        $admin_user->setUsername($username);
        $admin_user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $admin_user->setActive(true);

        $admin_userRepository = $this->createUserRepository($admin_user);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $admin_userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNotNull($response);
    }

    public function testLoginWithDefaultLoginEnabledAndWrongPassword(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $admin_user = new AdminUserEntity();
        $admin_user->setId(Uuid::randomBytes());
        $admin_user->setUsername($username);
        $admin_user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $admin_user->setActive(true);

        $admin_userRepository = $this->createUserRepository($admin_user);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $admin_userRepository->getUserEntityByUserCredentials(
            $username,
            'secure-test-wrong',
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithDefaultLoginEnabledAndNoUserFound(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $admin_userRepository = $this->createUserRepository(null);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $admin_userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithDefaultLoginDisabled(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $admin_user = new AdminUserEntity();
        $admin_user->setId(Uuid::randomBytes());
        $admin_user->setUsername($username);
        $admin_user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $admin_user->setActive(true);

        $admin_userRepository = $this->createUserRepository($admin_user, false);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $admin_userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithDefaultLoginEnabledAndInactiveUser(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $admin_user = new AdminUserEntity();
        $admin_user->setId(Uuid::randomBytes());
        $admin_user->setUsername($username);
        $admin_user->setPassword(password_hash($password, \PASSWORD_BCRYPT));
        $admin_user->setActive(false);

        $admin_userRepository = $this->createUserRepository($admin_user);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $admin_userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    protected function createUserRepository(?AdminUserEntity $admin_user, bool $useDefault = true): AdminUserRepository
    {
        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('fetchAssociative')->willReturnCallback(static function () use ($admin_user) {
            if ($admin_user !== null) {
                return $admin_user->jsonSerialize();
            }

            return false;
        });

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $loginConfigService = new LoginConfigService(
            [
                'use_default' => $useDefault,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'http://redirect.uri',
                'base_url' => 'http://base.uri',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            static::createStub(RouterInterface::class)
        );

        return new AdminUserRepository($connection, $loginConfigService);
    }
}
