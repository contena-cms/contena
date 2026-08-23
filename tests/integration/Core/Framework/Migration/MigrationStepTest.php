<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
class MigrationStepTest extends TestCase
{
    use KernelTestBehaviour;

    public function testForeignKeyExists(): void
    {
        $connection = $this->getConnection();
        $step = $this->getStep();

        $step->doDropTableIfExists($connection, 'test_table');

        $connection->executeStatement(<<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk.test_table.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        static::assertTrue($step->doForeignKeyExists($connection, 'test_table', 'fk.test_table.user_id'));
        static::assertFalse($step->doForeignKeyExists($connection, 'test_table', 'fk.test_table.missing'));

        $step->doDropTableIfExists($connection, 'test_table');
    }

    public function testDropForeignKey(): void
    {
        $connection = $this->getConnection();
        $step = $this->getStep();

        $step->doDropTableIfExists($connection, 'test_table');

        $this->getConnection()->executeStatement(<<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `test_table`
    ADD CONSTRAINT `fk.test_table.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);
SQL);

        // table does not exist
        static::assertFalse($step->doDropForeignKeyIfExists($connection, 'test', 'test'));
        // column does not exist
        static::assertFalse($step->doDropForeignKeyIfExists($connection, 'user', 'test'));

        static::assertTrue($step->doDropForeignKeyIfExists($connection, 'test_table', 'fk.test_table.user_id'));

        $step->doDropTableIfExists($connection, 'test_table');
    }

    public function testDropIndex(): void
    {
        $connection = $this->getConnection();
        $step = $this->getStep();

        $step->doDropTableIfExists($connection, 'test_table');

        $this->getConnection()->executeStatement(<<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `test_table`
    ADD INDEX `idx.user_id` (`user_id`);
SQL);

        // table does not exist
        static::assertFalse($step->doDropIndexIfExists($connection, 'test', 'test'));
        // column does not exist
        static::assertFalse($step->doDropIndexIfExists($connection, 'user', 'test'));

        static::assertTrue($step->doDropIndexIfExists($connection, 'test_table', 'idx.user_id'));

        $step->doDropTableIfExists($connection, 'test_table');
    }

    public function testDropColumn(): void
    {
        $connection = $this->getConnection();
        $step = $this->getStep();

        $step->doDropTableIfExists($connection, 'test_table');

        $this->getConnection()->executeStatement(<<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // table does not exist
        static::assertFalse($step->doDropColumnIfExists($connection, 'test', 'test'));

        // column does not exist
        static::assertFalse($step->doDropColumnIfExists($connection, 'test_table', 'test'));

        static::assertTrue($step->doDropColumnIfExists($connection, 'test_table', 'user_id'));

        $step->doDropTableIfExists($connection, 'test_table');
    }

    public function testDropTable(): void
    {
        $connection = $this->getConnection();
        $step = $this->getStep();

        $step->doDropTableIfExists($connection, 'test_table');

        $this->getConnection()->executeStatement(
            <<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $step->doDropTableIfExists($connection, 'test_table');

        static::assertFalse(TableHelper::tableExists($connection, 'test_table'));
    }

    public function testAddColumn(): void
    {
        $connection = $this->getConnection();
        $step = $this->getStep();

        $step->doDropTableIfExists($connection, 'test_table');

        $this->getConnection()->executeStatement(
            <<<'SQL'
CREATE TABLE `test_table` (
    `id` BINARY(16) NOT NULL,
    `user_id` BINARY(16) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        static::assertTrue($step->doAddColumn($connection, 'test_table', 'test_column', 'VARCHAR(255)'));

        static::assertFalse($step->doAddColumn($connection, 'test_table', 'test_column', 'VARCHAR(255)'));

        $step->doDropTableIfExists($connection, 'test_table');

        // columnExists() returns false for a missing table, the ALTER TABLE of addColumn() then fails
        static::assertFalse($step->doColumnExists($connection, 'foo', 'test_column'));

        $this->expectException(TableNotFoundException::class);

        static::assertTrue($step->doAddColumn($connection, 'foo', 'test_column', 'VARCHAR(255)'));
    }

    private function getStep(): ExampleStep
    {
        return new ExampleStep();
    }

    private function getConnection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}

/**
 * @internal
 */
class ExampleStep extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return time();
    }

    public function update(Connection $connection): void
    {
    }

    public function doDropTableIfExists(Connection $connection, string $table): void
    {
        $this->dropTableIfExists($connection, $table);
    }

    /**
     * @param non-empty-string $table
     */
    public function doForeignKeyExists(Connection $connection, string $table, string $foreignKey): bool
    {
        return $this->foreignKeyExists($connection, $table, $foreignKey);
    }

    public function doDropForeignKeyIfExists(Connection $connection, string $table, string $column): bool
    {
        return $this->dropForeignKeyIfExists($connection, $table, $column);
    }

    public function doDropIndexIfExists(Connection $connection, string $table, string $index): bool
    {
        return $this->dropIndexIfExists($connection, $table, $index);
    }

    public function doDropColumnIfExists(Connection $connection, string $table, string $column): bool
    {
        return $this->dropColumnIfExists($connection, $table, $column);
    }

    /**
     * @param non-empty-string $table
     */
    public function doAddColumn(Connection $connection, string $table, string $column, string $type): bool
    {
        return $this->addColumn($connection, $table, $column, $type);
    }

    /**
     * @param non-empty-string $table
     */
    public function doColumnExists(Connection $connection, string $table, string $column): bool
    {
        return $this->columnExists($connection, $table, $column);
    }
}
