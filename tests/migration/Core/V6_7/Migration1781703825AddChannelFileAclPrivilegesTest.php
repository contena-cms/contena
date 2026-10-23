<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1781703825AddChannelFileAclPrivileges;

/**
 * @internal
 */
#[CoversClass(Migration1781703825AddChannelFileAclPrivileges::class)]
class Migration1781703825AddChannelFileAclPrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;


    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1781703825AddChannelFileAclPrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1781703825, $this->migration->getCreationTimestamp());
    }

    public function testAddsChannelFilePrivilegesToChannelRoles(): void
    {
        $viewerRoleId = $this->createRole('viewer', ['channel.viewer']);
        $editorRoleId = $this->createRole('editor', ['channel.editor']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $viewerPrivileges = $this->fetchPrivileges($viewerRoleId);
        static::assertContains('channel.viewer', $viewerPrivileges);
        static::assertContains('channel_file:read', $viewerPrivileges);
        static::assertCount(1, \array_keys($viewerPrivileges, 'channel_file:read', true));

        $editorPrivileges = $this->fetchPrivileges($editorRoleId);
        static::assertContains('channel.editor', $editorPrivileges);
        static::assertContains('channel_file:create', $editorPrivileges);
        static::assertContains('channel_file:update', $editorPrivileges);
        static::assertCount(1, \array_keys($editorPrivileges, 'channel_file:create', true));
        static::assertCount(1, \array_keys($editorPrivileges, 'channel_file:update', true));
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole('unrelated', ['category.viewer']);
        $before = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        $this->migration->update($this->connection);

        $after = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        static::assertSame($before, $after);
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(string $name, array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test sales channel file acl migration ' . $name,
            'privileges' => \json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    /**
     * @return list<string>
     */
    private function fetchPrivileges(string $roleId): array
    {
        $privileges = $this->connection->fetchOne(
            'SELECT `privileges` FROM `acl_role` WHERE id = :id',
            ['id' => $roleId]
        );

        static::assertIsString($privileges);

        $decodedPrivileges = \json_decode($privileges, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($decodedPrivileges);
        static::assertTrue(\array_is_list($decodedPrivileges));

        foreach ($decodedPrivileges as $decodedPrivilege) {
            static::assertIsString($decodedPrivilege);
        }

        /** @var list<string> $decodedPrivileges */
        return $decodedPrivileges;
    }
}
