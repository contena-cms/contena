<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1788531955MakeBlogGuaranteeConfirmedInheritable;

/**
 * @internal
 */
#[CoversClass(Migration1788531955MakeBlogGuaranteeConfirmedInheritable::class)]
class Migration1788531955MakeBlogGuaranteeConfirmedInheritableTest extends TestCase
{
    private Connection $connection;

    private string $versionId;

    private string $parentId;

    private string $untouchedVariantId;

    private string $confirmedVariantId;

    private string $singleBlogId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $this->parentId = Uuid::randomBytes();
        $this->untouchedVariantId = Uuid::randomBytes();
        $this->confirmedVariantId = Uuid::randomBytes();
        $this->singleBlogId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `blog` WHERE `id` IN (:ids)',
            ['ids' => [$this->parentId, $this->untouchedVariantId, $this->confirmedVariantId, $this->singleBlogId]],
            ['ids' => ArrayParameterType::BINARY]
        );

        // DDL is not rolled back, so leave the column as a fully migrated database has it.
        (new Migration1788531955MakeBlogGuaranteeConfirmedInheritable())->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1788531955,
            (new Migration1788531955MakeBlogGuaranteeConfirmedInheritable())->getCreationTimestamp()
        );
    }

    public function testMigrationMakesGuaranteeConfirmedNullable(): void
    {
        $this->restoreColumnAsAddedByGaranLabelMigration();

        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'blog', 'guarantee_confirmed')->isNotNull);

        $migration = new Migration1788531955MakeBlogGuaranteeConfirmedInheritable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'blog', 'guarantee_confirmed');

        static::assertFalse($column->isNotNull);
        static::assertNull($column->defaultValue);
    }

    public function testMigrationLetsVariantsWithoutOwnValueFallBackToTheParent(): void
    {
        $this->restoreColumnAsAddedByGaranLabelMigration();
        $this->createBlogs();

        $migration = new Migration1788531955MakeBlogGuaranteeConfirmedInheritable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $values = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `guarantee_confirmed` FROM `blog` WHERE `id` IN (:ids)',
            ['ids' => [$this->parentId, $this->untouchedVariantId, $this->confirmedVariantId, $this->singleBlogId]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertNull($values[Uuid::fromBytesToHex($this->untouchedVariantId)]);
        static::assertSame('1', $values[Uuid::fromBytesToHex($this->confirmedVariantId)]);
        static::assertSame('1', $values[Uuid::fromBytesToHex($this->parentId)]);
        static::assertSame('0', $values[Uuid::fromBytesToHex($this->singleBlogId)]);
    }

    /**
     * Migration1783944800AddGaranLabel added the column as NOT NULL DEFAULT 0, which is the state
     * this migration corrects. How far the shared test database has been migrated is unknown, so
     * recreate that state explicitly.
     */
    private function restoreColumnAsAddedByGaranLabelMigration(): void
    {
        if (TableHelper::columnExists($this->connection, 'blog', 'guarantee_confirmed')) {
            $this->connection->executeStatement('ALTER TABLE `blog` DROP COLUMN `guarantee_confirmed`');
        }

        $this->connection->executeStatement('ALTER TABLE `blog` ADD COLUMN `guarantee_confirmed` TINYINT(1) NOT NULL DEFAULT \'0\'');
    }

    private function createBlogs(): void
    {
        $this->connection->insert('blog', [
            'id' => $this->parentId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue = new MultiInsertQueryQueue($this->connection);

        $queue->addInsert('blog', [
            'id' => $this->untouchedVariantId,
            'version_id' => $this->versionId,
            'parent_id' => $this->parentId,
            'parent_version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('blog', [
            'id' => $this->confirmedVariantId,
            'version_id' => $this->versionId,
            'parent_id' => $this->parentId,
            'parent_version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('blog', [
            'id' => $this->singleBlogId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'guarantee_confirmed' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();
    }
}
