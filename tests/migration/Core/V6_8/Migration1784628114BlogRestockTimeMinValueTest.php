<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1784628114BlogRestockTimeMinValue;

/**
 * @internal
 */
#[CoversClass(Migration1784628114BlogRestockTimeMinValue::class)]
class Migration1784628114BlogRestockTimeMinValueTest extends TestCase
{
    private Connection $connection;

    private string $versionId;

    private string $negativeRestockBlogId;

    private string $zeroRestockBlogId;

    private string $positiveRestockBlogId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $this->negativeRestockBlogId = Uuid::randomBytes();
        $this->zeroRestockBlogId = Uuid::randomBytes();
        $this->positiveRestockBlogId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `blog` WHERE `id` IN (:ids)',
            ['ids' => [$this->negativeRestockBlogId, $this->zeroRestockBlogId, $this->positiveRestockBlogId]],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1784628114BlogRestockTimeMinValue();

        static::assertSame(1784628114, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $queue = new MultiInsertQueryQueue($this->connection);

        $queue->addInsert('blog', [
            'id' => $this->negativeRestockBlogId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'restock_time' => -5,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('blog', [
            'id' => $this->zeroRestockBlogId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'restock_time' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('blog', [
            'id' => $this->positiveRestockBlogId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'restock_time' => 3,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();

        $migration = new Migration1784628114BlogRestockTimeMinValue();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        $restockTimes = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `restock_time` FROM `blog` WHERE `id` IN (:ids)',
            ['ids' => [$this->negativeRestockBlogId, $this->zeroRestockBlogId, $this->positiveRestockBlogId]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertNull($restockTimes[Uuid::fromBytesToHex($this->negativeRestockBlogId)]);
        static::assertSame('0', $restockTimes[Uuid::fromBytesToHex($this->zeroRestockBlogId)]);
        static::assertSame('3', $restockTimes[Uuid::fromBytesToHex($this->positiveRestockBlogId)]);
    }
}
