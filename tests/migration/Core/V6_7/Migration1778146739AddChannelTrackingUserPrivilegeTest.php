<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1778146739AddChannelTrackingUserPrivilege;
use Contena\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1778146739AddChannelTrackingUserPrivilege::class)]
class Migration1778146739AddChannelTrackingUserPrivilegeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1778146739AddChannelTrackingUserPrivilege $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1778146739AddChannelTrackingUserPrivilege();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1778146739, $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->prepareTestData();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->connection->fetchOne(
            'SELECT privileges FROM acl_role WHERE name = :name',
            ['name' => 'User Viewer']
        );

        static::assertNotFalse($privileges);
        $decodedPrivileges = json_decode($privileges, true);

        static::assertContains(
            'channel_tracking_customer:read',
            $decodedPrivileges
        );
    }

    private function prepareTestData(): void
    {
        $this->connection->insert('acl_role', [
            'id' => Uuid::randomBytes(),
            'name' => 'User Viewer',
            'privileges' => json_encode(['user.viewer']),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }
}
