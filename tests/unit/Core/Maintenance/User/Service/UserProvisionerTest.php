<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\User\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\Test\Stub\Doctrine\FakeQueryBuilder;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(UserProvisioner::class)]
class UserProvisionerTest extends TestCase
{
    public function testProvision(): void
    {
        $localeId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with(
                'user',
                static::callback(static function (array $data) use ($localeId): bool {
                    static::assertSame('admin', $data['username']);
                    static::assertSame('first last', $data['name']);
                    static::assertSame('test@test.com', $data['email']);
                    static::assertSame($localeId, $data['locale_id']);
                    static::assertSame(1, $data['admin']);
                    static::assertTrue($data['active']);
                    static::assertSame('Asia/Shanghai', $data['time_zone']);

                    return password_verify('contenaAdmin', (string) $data['password']);
                })
            );
        $connection->expects($this->once())->method('fetchOne')->willReturn(json_encode(['_value' => 8], \JSON_THROW_ON_ERROR));
        $connection->expects($this->exactly(2))->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $user = [
            'name' => 'first last',
            'email' => 'test@test.com',
        ];

        $provisioner = new UserProvisioner($connection, new NativeClock(), $this->createNumberRangeGenerator());
        $provisioner->provision('admin', 'contenaAdmin', $user);
    }

    public function testProvisionThrowsIfUserAlreadyExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('insert');

        $connection->expects($this->once())->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, [[Uuid::randomBytes()]]),
        );

        $user = [
            'name' => 'first last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection, new NativeClock(), $this->createNumberRangeGenerator());
        $this->expectExceptionObject(new \RuntimeException('User with username "admin" already exists.'));
        $provisioner->provision('admin', 'contenaAdmin', $user);
    }

    public function testProvisionAssignsRole(): void
    {
        $localeId = Uuid::randomBytes();
        $roleId = Uuid::randomBytes();
        $userId = null;
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data) use (&$userId, $roleId): int {
                if ($table === 'user') {
                    $userId = $data['id'];
                    static::assertSame('admin', $data['username']);
                    static::assertSame(0, $data['admin']);

                    return 1;
                }

                static::assertSame('acl_user_role', $table);
                static::assertSame($userId, $data['user_id']);
                static::assertSame($roleId, $data['acl_role_id']);

                return 1;
            });
        $connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(json_encode(['_value' => 8], \JSON_THROW_ON_ERROR), $roleId);
        $connection->expects($this->exactly(2))->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $provisioner = new UserProvisioner($connection, new NativeClock(), $this->createNumberRangeGenerator());
        $provisioner->provision('admin', 'contenaAdmin', [
            'email' => UserProvisioner::DEFAULT_ADMIN_EMAIL,
            'admin' => false,
            'roleCode' => UserProvisioner::ADMINISTRATOR_ROLE_CODE,
        ]);
    }

    public function testProvisionThrowsIfPasswordTooShort(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('insert');

        $connection->expects($this->once())->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
        );

        $connection->expects($this->once())->method('fetchOne')->willReturn(json_encode(['_value' => 8], \JSON_THROW_ON_ERROR));

        $user = [
            'name' => 'first last',
            'email' => 'test@test.com',
            'admin' => false,
        ];

        $provisioner = new UserProvisioner($connection, new NativeClock(), $this->createNumberRangeGenerator());
        $this->expectExceptionObject(MaintenanceException::passwordTooShort(8));
        $provisioner->provision('admin', 'short', $user);
    }

    private function createNumberRangeGenerator(): AbstractNumberRangeValueGenerator
    {
        $generator = static::createStub(AbstractNumberRangeValueGenerator::class);
        $generator->method('getValue')->willReturn('10000');

        return $generator;
    }
}
