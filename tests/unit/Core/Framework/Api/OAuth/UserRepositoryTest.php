<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\OAuth\UserRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(UserRepository::class)]
class UserRepositoryTest extends TestCase
{
    public function testLoginWithCorrectCredentials(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $userRepository = $this->createUserRepository([
            'id' => Uuid::randomBytes(),
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'active' => true,
        ]);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNotNull($response);
    }

    public function testLoginWithWrongPassword(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $userRepository = $this->createUserRepository([
            'id' => Uuid::randomBytes(),
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'active' => true,
        ]);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            'secure-test-wrong',
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithNoUserFound(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $userRepository = $this->createUserRepository(null);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    public function testLoginWithInactiveUser(): void
    {
        $username = 'my_username';
        $password = 'secure-test';

        $userRepository = $this->createUserRepository([
            'id' => Uuid::randomBytes(),
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'active' => false,
        ]);

        $clientEntity = static::createStub(ClientEntityInterface::class);
        $response = $userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            'password',
            $clientEntity
        );

        static::assertNull($response);
    }

    /**
     * @param array{id: string, password: string, active: bool}|null $user
     */
    protected function createUserRepository(?array $user): UserRepository
    {
        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('fetchAssociative')->willReturn($user ?? false);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new UserRepository($connection, new MockClock('2026-08-03 10:00:00'), new RequestStack());
    }
}
