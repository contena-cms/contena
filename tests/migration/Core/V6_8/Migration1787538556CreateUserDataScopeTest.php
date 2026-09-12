<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1787538556CreateUserDataScope;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1787538556CreateUserDataScope::class)]
class Migration1787538556CreateUserDataScopeTest extends TestCase
{
    private const array SCOPED_USER_RELATIONS = [
        'acl_user_role',
        'user_position',
        'user_tag',
        'user_config',
    ];

    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $userIds = [];

    /**
     * @var list<string>
     */
    private array $dataScopeIds = [];

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        foreach ($this->userIds as $userId) {
            $this->connection->delete('user', ['id' => $userId]);
        }
        foreach ($this->dataScopeIds as $dataScopeId) {
            $this->connection->delete('data_scope', ['id' => $dataScopeId]);
        }
    }

    public function testCreatesExplicitGrantBoundaryIdempotently(): void
    {
        $migration = new Migration1787538556CreateUserDataScope();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['user_id', 'data_scope_id', 'active', 'admin', 'read_all_scopes', 'user_code'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'user_data_scope', $column));
        }
        static::assertTrue(TableHelper::indexExists(
            $this->connection,
            'user_data_scope',
            'uniq.user_data_scope.data_scope_id_user_code',
        ));
        foreach (self::SCOPED_USER_RELATIONS as $table) {
            static::assertTrue(TableHelper::indexExists(
                $this->connection,
                $table,
                'idx.' . $table . '.user_id_data_scope_id',
            ));
            static::assertTrue(TableHelper::foreignKeyExists(
                $this->connection,
                $table,
                'fk.' . $table . '.user_data_scope',
            ));
        }
    }

    public function testReadAllScopesCanOnlyBeGrantedOnPlatform(): void
    {
        $userId = $this->insertUser();
        $tenantDataScopeId = $this->insertTenantDataScope();

        $this->insertGrant($userId, Defaults::PLATFORM_DATA_SCOPE, readAllScopes: true);
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT read_all_scopes FROM user_data_scope WHERE user_id = :userId AND data_scope_id = :dataScopeId',
            [
                'userId' => $userId,
                'dataScopeId' => Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE),
            ],
        ));

        $this->expectException(Exception::class);
        $this->insertGrant($userId, Uuid::fromBytesToHex($tenantDataScopeId), readAllScopes: true);
    }

    public function testDeletingGrantCascadesOnlyRelationsInThatDataScope(): void
    {
        $userId = $this->insertUser();
        $tenantDataScopeId = $this->insertTenantDataScope();
        $platformDataScopeId = Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE);
        $this->insertGrant($userId, Defaults::PLATFORM_DATA_SCOPE);
        $this->insertGrant($userId, Uuid::fromBytesToHex($tenantDataScopeId));

        $now = new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        foreach ([$platformDataScopeId, $tenantDataScopeId] as $dataScopeId) {
            $this->connection->insert('user_config', [
                'data_scope_id' => $dataScopeId,
                'id' => Uuid::randomBytes(),
                'user_id' => $userId,
                'key' => 'grant-cascade',
                'value' => '{}',
                'created_at' => $now,
            ]);
        }

        $this->connection->delete('user_data_scope', [
            'user_id' => $userId,
            'data_scope_id' => $tenantDataScopeId,
        ]);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_config WHERE user_id = :userId AND data_scope_id = :dataScopeId',
            ['userId' => $userId, 'dataScopeId' => $platformDataScopeId],
        ));
        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM user_config WHERE user_id = :userId AND data_scope_id = :dataScopeId',
            ['userId' => $userId, 'dataScopeId' => $tenantDataScopeId],
        ));
    }

    private function insertUser(): string
    {
        $userId = Uuid::randomBytes();
        $this->userIds[] = $userId;
        $suffix = \bin2hex(\random_bytes(4));
        $localeId = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
        static::assertIsString($localeId);

        $this->connection->insert('user', [
            'id' => $userId,
            'locale_id' => $localeId,
            'username' => 'scope-migration-' . $suffix,
            'password' => 'not-used',
            'name' => 'Scope migration user',
            'email' => 'scope-migration-' . $suffix . '@example.invalid',
            'active' => 1,
            'time_zone' => Defaults::DEFAULT_TIME_ZONE,
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $userId;
    }

    private function insertTenantDataScope(): string
    {
        $dataScopeId = Uuid::randomBytes();
        $this->dataScopeIds[] = $dataScopeId;
        $this->connection->insert('data_scope', ['id' => $dataScopeId, 'type' => 'tenant']);

        return $dataScopeId;
    }

    private function insertGrant(string $userId, string $dataScopeId, bool $readAllScopes = false): void
    {
        $this->connection->insert('user_data_scope', [
            'user_id' => $userId,
            'data_scope_id' => Uuid::fromHexToBytes($dataScopeId),
            'active' => 1,
            'admin' => 0,
            'read_all_scopes' => (int) $readAllScopes,
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
