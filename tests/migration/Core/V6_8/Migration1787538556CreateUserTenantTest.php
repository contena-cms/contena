<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1787538556CreateUserTenant;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1787538556CreateUserTenant::class)]
class Migration1787538556CreateUserTenantTest extends TestCase
{
    private const array TENANT_USER_TABLES = ['acl_user_role', 'user_position', 'user_tag', 'user_config'];

    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $userIds = [];

    /**
     * @var list<string>
     */
    private array $tenantIds = [];

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TENANT_USER_TABLES as $table) {
            $foreignKey = 'fk.' . $table . '.user_tenant';
            if (TableHelper::foreignKeyExists($this->connection, $table, $foreignKey)) {
                $this->connection->executeStatement(\sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $foreignKey));
            }
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `user_tenant`');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        foreach ($this->userIds as $userId) {
            $this->connection->delete('user', ['id' => $userId]);
        }
        foreach ($this->tenantIds as $tenantId) {
            $this->connection->delete('tenant', ['id' => $tenantId]);
        }

        new Migration1787538556CreateUserTenant()->update($this->connection);
    }

    public function testCreatesAndBackfillsMembershipsIdempotently(): void
    {
        $tenantId = Uuid::randomBytes();
        $tenantUserId = Uuid::randomBytes();
        $platformUserId = Uuid::randomBytes();
        $relatedUserId = Uuid::randomBytes();
        $this->tenantIds[] = $tenantId;
        $this->userIds = [$tenantUserId, $platformUserId, $relatedUserId];
        $localeId = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
        static::assertIsString($localeId);

        $this->connection->insert('tenant', [
            'id' => $tenantId,
            'name' => 'User tenant migration',
            'code' => 'user-tenant-' . bin2hex(random_bytes(4)),
            'status' => 1,
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $suffix = bin2hex(random_bytes(4));
        $this->insertUser($tenantUserId, $tenantId, $localeId, 'tenant-' . $suffix, false, true, 'T-' . $suffix);
        $this->insertUser($platformUserId, null, $localeId, 'platform-' . $suffix, true, false, 'P-' . $suffix);
        $this->insertUser($relatedUserId, null, $localeId, 'related-' . $suffix, true, false, 'R-' . $suffix);
        $this->connection->executeStatement(
            'INSERT INTO `user_config` (`tenant_id`, `id`, `user_id`, `key`, `value`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                Uuid::randomBytes(),
                $relatedUserId,
                'migration-related-' . $suffix,
                '{}',
                new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
        $this->connection->insert('user_access_key', [
            'tenant_id' => $tenantId,
            'id' => Uuid::randomBytes(),
            'user_id' => $tenantUserId,
            'access_key' => 'migration-' . $suffix,
            'secret_access_key' => 'migration-secret-' . $suffix,
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->insert('user_recovery', [
            'tenant_id' => $tenantId,
            'id' => Uuid::randomBytes(),
            'user_id' => $tenantUserId,
            'hash' => 'migration-recovery-' . $suffix,
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1787538556CreateUserTenant();
        $migration->update($this->connection);
        $this->connection->update('user_tenant', [
            'active' => 1,
            'admin' => 0,
            'user_code' => 'CUSTOM-' . $suffix,
        ], ['user_id' => $tenantUserId, 'tenant_id' => $tenantId]);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'user_tenant', 'user_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'user_tenant', 'tenant_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'user_tenant', 'active'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'user_tenant', 'admin'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'user_tenant', 'user_code'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'user_tenant', 'uniq.user_tenant.tenant_id_user_code'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'user_config', 'platform_key'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'user_config', 'uniq.user_config.tenant_user_key'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'user_config', 'uniq.user_config.platform_user_key'));
        static::assertFalse(TableHelper::indexExists($this->connection, 'user_config', 'uniq.user_id_key'));
        foreach (self::TENANT_USER_TABLES as $table) {
            static::assertTrue(TableHelper::indexExists($this->connection, $table, 'idx.' . $table . '.user_id_tenant_id'));
            static::assertTrue(TableHelper::foreignKeyExists($this->connection, $table, 'fk.' . $table . '.user_tenant'));
        }

        $membership = $this->connection->fetchAssociative(
            'SELECT active, admin, user_code FROM user_tenant WHERE user_id = :userId AND tenant_id = :tenantId',
            ['userId' => $tenantUserId, 'tenantId' => $tenantId],
        );
        static::assertSame(['active' => '1', 'admin' => '0', 'user_code' => 'CUSTOM-' . $suffix], $membership);
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_tenant WHERE user_id = :userId AND tenant_id = :tenantId',
            ['userId' => $relatedUserId, 'tenantId' => $tenantId],
        ));
        static::assertNull($this->connection->fetchOne('SELECT tenant_id FROM user_access_key WHERE user_id = :userId', ['userId' => $tenantUserId]));
        static::assertNull($this->connection->fetchOne('SELECT tenant_id FROM user_recovery WHERE user_id = :userId', ['userId' => $tenantUserId]));
        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_tenant WHERE user_id = :userId',
            ['userId' => $platformUserId],
        ));
    }

    public function testDeletingMembershipCascadesOnlyItsTenantRelations(): void
    {
        $tenantA = Uuid::randomBytes();
        $tenantB = Uuid::randomBytes();
        $userId = Uuid::randomBytes();
        $this->tenantIds = [$tenantA, $tenantB];
        $this->userIds[] = $userId;
        $localeId = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
        static::assertIsString($localeId);

        foreach ([[$tenantA, 'a'], [$tenantB, 'b']] as [$tenantId, $suffix]) {
            $this->connection->insert('tenant', [
                'id' => $tenantId,
                'name' => 'Membership cascade ' . $suffix,
                'code' => 'membership-cascade-' . $suffix . '-' . bin2hex(random_bytes(4)),
                'status' => 1,
                'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
        $suffix = bin2hex(random_bytes(4));
        $this->insertUser($userId, null, $localeId, 'cascade-' . $suffix, true, false, 'P-' . $suffix);

        $migration = new Migration1787538556CreateUserTenant();
        $migration->update($this->connection);
        foreach ([$tenantA, $tenantB] as $tenantId) {
            $this->connection->insert('user_tenant', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'active' => 1,
                'admin' => 0,
                'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $this->insertTenantRelations($userId, $tenantA, 'shared-key');
        $this->insertTenantRelations($userId, $tenantB, 'shared-key');

        $this->connection->delete('user_tenant', ['user_id' => $userId, 'tenant_id' => $tenantA]);

        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `user` WHERE id = :userId', ['userId' => $userId]));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tenant WHERE user_id = :userId AND tenant_id = :tenantId', ['userId' => $userId, 'tenantId' => $tenantA]));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tenant WHERE user_id = :userId AND tenant_id = :tenantId', ['userId' => $userId, 'tenantId' => $tenantB]));
        foreach (self::TENANT_USER_TABLES as $table) {
            static::assertSame(0, (int) $this->connection->fetchOne(
                \sprintf('SELECT COUNT(*) FROM `%s` WHERE user_id = :userId AND tenant_id = :tenantId', $table),
                ['userId' => $userId, 'tenantId' => $tenantA],
            ));
            static::assertSame(1, (int) $this->connection->fetchOne(
                \sprintf('SELECT COUNT(*) FROM `%s` WHERE user_id = :userId AND tenant_id = :tenantId', $table),
                ['userId' => $userId, 'tenantId' => $tenantB],
            ));
        }
    }

    private function insertTenantRelations(string $userId, string $tenantId, string $configKey): void
    {
        $now = new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->insert('acl_user_role', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'acl_role_id' => Uuid::randomBytes(),
            'created_at' => $now,
        ]);
        $this->connection->insert('user_position', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'position_id' => Uuid::randomBytes(),
        ]);
        $this->connection->insert('user_tag', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tag_id' => Uuid::randomBytes(),
            'created_at' => $now,
        ]);
        $this->connection->executeStatement(
            'INSERT INTO `user_config` (`tenant_id`, `id`, `user_id`, `key`, `value`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, Uuid::randomBytes(), $userId, $configKey, '{}', $now],
        );
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function insertUser(
        string $id,
        ?string $tenantId,
        string $localeId,
        string $suffix,
        bool $active,
        bool $admin,
        string $userCode,
    ): void {
        $this->connection->insert('user', [
            'id' => $id,
            'tenant_id' => $tenantId,
            'locale_id' => $localeId,
            'user_code' => $userCode,
            'username' => 'migration-' . $suffix . '-' . bin2hex(random_bytes(4)),
            'password' => 'not-used',
            'name' => 'Migration user',
            'email' => 'migration-' . $suffix . '-' . bin2hex(random_bytes(4)) . '@example.com',
            'active' => (int) $active,
            'admin' => (int) $admin,
            'time_zone' => Defaults::DEFAULT_TIME_ZONE,
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
