<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1778487892AddBlogTranslationReadPrivilege;

/**
 * @internal
 */
#[CoversClass(Migration1778487892AddBlogTranslationReadPrivilege::class)]
class Migration1778487892AddBlogTranslationReadPrivilegeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1778487892AddBlogTranslationReadPrivilege $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1778487892AddBlogTranslationReadPrivilege();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1778487892, $this->migration->getCreationTimestamp());
    }

    public function testAddsBlogTranslationReadToBlogViewerRoles(): void
    {
        $roleId = $this->createRole(['blog.viewer']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains('blog.viewer', $privileges);
        static::assertContains('blog_translation:read', $privileges);
        static::assertCount(1, \array_keys($privileges, 'blog_translation:read', true));
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole(['category.viewer']);
        $before = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        $this->migration->update($this->connection);

        $after = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        static::assertSame($before, $after);
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test blog acl migration',
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
