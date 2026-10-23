<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1768545321RevocationRequestFlow;

/**
 * @internal
 */
#[CoversClass(Migration1768545321RevocationRequestFlow::class)]
class Migration1768545321RevocationRequestFlowTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1768545321, (new Migration1768545321RevocationRequestFlow())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $migration = new Migration1768545321RevocationRequestFlow();

        static::assertTrue($this->hasFlowEntry());
        $this->dropFlowEntry();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->hasFlowEntry());
    }

    private function hasFlowEntry(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `flow` WHERE `id` = :flowId',
            ['flowId' => Uuid::fromHexToBytes(Migration1768545321RevocationRequestFlow::REVOCATION_REQUEST_FLOW_ID)]
        );
    }

    private function dropFlowEntry(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `flow` WHERE `id` = :flowId',
            ['flowId' => Uuid::fromHexToBytes(Migration1768545321RevocationRequestFlow::REVOCATION_REQUEST_FLOW_ID)]
        );

        static::assertFalse($this->hasFlowEntry());
    }
}
