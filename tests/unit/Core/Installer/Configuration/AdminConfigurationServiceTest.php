<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Installer\Configuration\AdminConfigurationService;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\Test\Stub\Doctrine\FakeQueryBuilder;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(AdminConfigurationService::class)]
class AdminConfigurationServiceTest extends TestCase
{
    public function testCreateAdmin(): void
    {
        $localeId = Uuid::randomBytes();
        $roleId = Uuid::randomBytes();
        $users = [];
        $roleAssignment = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data) use (&$users, &$roleAssignment): int {
                if ($table === 'user') {
                    $users[] = $data;

                    return 1;
                }

                static::assertSame('acl_user_role', $table);
                $roleAssignment = $data;

                return 1;
            });

        $connection->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(json_encode(['_value' => 8]), json_encode(['_value' => 8]), $roleId);

        $connection->expects($this->exactly(4))->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]]),
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $user = [
            'username' => 'supperadmin',
            'password' => 'contenaAdmin',
            'name' => 'first last',
            'email' => 'test@test.com',
        ];

        $generator = static::createStub(AbstractNumberRangeValueGenerator::class);
        $generator->method('getValue')->willReturn('10000');

        $service = new AdminConfigurationService(new NativeClock(), $generator);
        $service->createAdmin($user, $connection);

        static::assertCount(2, $users);
        static::assertSame('supperadmin', $users[0]['username']);
        static::assertSame('first last', $users[0]['name']);
        static::assertSame('test@test.com', $users[0]['email']);
        static::assertSame($localeId, $users[0]['locale_id']);
        static::assertSame(1, $users[0]['admin']);
        static::assertTrue($users[0]['active']);
        static::assertTrue(password_verify('contenaAdmin', (string) $users[0]['password']));

        static::assertSame(UserProvisioner::DEFAULT_ADMIN_USERNAME, $users[1]['username']);
        static::assertSame(UserProvisioner::DEFAULT_ADMIN_USERNAME, $users[1]['name']);
        static::assertSame(UserProvisioner::DEFAULT_ADMIN_EMAIL, $users[1]['email']);
        static::assertSame($localeId, $users[1]['locale_id']);
        static::assertSame(0, $users[1]['admin']);
        static::assertTrue($users[1]['active']);
        static::assertTrue(password_verify('contenaAdmin', (string) $users[1]['password']));

        static::assertIsArray($roleAssignment);
        static::assertSame($users[1]['id'], $roleAssignment['user_id']);
        static::assertSame($roleId, $roleAssignment['acl_role_id']);
    }

    public function testCreateAdminDoesNotDuplicateRequestedAdminUsername(): void
    {
        $localeId = Uuid::randomBytes();
        $roleId = Uuid::randomBytes();
        $user = null;
        $roleAssignment = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data) use (&$user, &$roleAssignment): int {
                if ($table === 'user') {
                    $user = $data;

                    return 1;
                }

                static::assertSame('acl_user_role', $table);
                $roleAssignment = $data;

                return 1;
            });
        $connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(json_encode(['_value' => 8]), $roleId);
        $connection->expects($this->exactly(2))->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $generator = static::createStub(AbstractNumberRangeValueGenerator::class);
        $generator->method('getValue')->willReturn('10000');

        $service = new AdminConfigurationService(new NativeClock(), $generator);
        $service->createAdmin([
            'username' => 'admin',
            'password' => 'contenaAdmin',
            'name' => 'Custom administrator',
            'email' => 'custom@example.com',
        ], $connection);

        static::assertIsArray($user);
        static::assertSame('admin', $user['username']);
        static::assertSame(1, $user['admin']);
        static::assertIsArray($roleAssignment);
        static::assertSame($user['id'], $roleAssignment['user_id']);
        static::assertSame($roleId, $roleAssignment['acl_role_id']);
    }
}
