<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Installer\Configuration\AdminConfigurationService;
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
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with(
                'admin_user',
                static::callback(static function (array $data) use ($localeId): bool {
                    static::assertSame('admin', $data['username']);
                    static::assertSame('first', $data['first_name']);
                    static::assertSame('last', $data['last_name']);
                    static::assertSame('test@test.com', $data['email']);
                    static::assertSame($localeId, $data['locale_id']);
                    static::assertTrue($data['admin']);
                    static::assertTrue($data['active']);

                    return password_verify('contena', (string) $data['password']);
                })
            );

        $connection->expects($this->once())->method('fetchOne')->willReturn(json_encode(['_value' => 8]));

        $connection->method('createQueryBuilder')->willReturnOnConsecutiveCalls(
            new FakeQueryBuilder($connection, []),
            new FakeQueryBuilder($connection, [[$localeId]])
        );

        $admin_user = [
            'username' => 'admin',
            'password' => 'contena',
            'name' => 'first',
            'lastName' => 'last',
            'email' => 'test@test.com',
        ];

        $service = new AdminConfigurationService(new NativeClock());
        $service->createAdmin($admin_user, $connection);
    }
}
