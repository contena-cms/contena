<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1775200001IncreaseBlogDisplayGroupLength;

/**
 * @internal
 */
#[CoversClass(Migration1775200001IncreaseBlogDisplayGroupLength::class)]
class Migration1775200001IncreaseBlogDisplayGroupLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1775200001, (new Migration1775200001IncreaseBlogDisplayGroupLength())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1775200001IncreaseBlogDisplayGroupLength();
        static::assertSame(1775200001, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, BlogDefinition::ENTITY_NAME, 'display_group');

        static::assertSame('string', $column->type);
        static::assertSame(64, $column->length);
        static::assertFalse($column->isNotNull);
        static::assertNull($column->defaultValue);
    }

    public function testMigrationSkipsAlterWhenDisplayGroupAlreadyAtLeast64(): void
    {
        $this->connection->executeStatement('ALTER TABLE `blog` MODIFY `display_group` VARCHAR(64) NULL');

        (new Migration1775200001IncreaseBlogDisplayGroupLength())->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, BlogDefinition::ENTITY_NAME, 'display_group');
        static::assertSame(64, $column->length);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `blog` MODIFY `display_group` VARCHAR(50) NULL');
    }
}
