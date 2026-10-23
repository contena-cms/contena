<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1756068710FixUserAddressLastNameLength;

/**
 * @internal
 */
#[CoversClass(Migration1756068710FixUserAddressLastNameLength::class)]
class Migration1756068710FixUserAddressLastNameLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1756068710FixUserAddressLastNameLength();
        static::assertSame(1756068710, $migration->getCreationTimestamp());
    }

    public function testMigrationChangesColumnLengthAndIsIdempotent(): void
    {
        $migration = new Migration1756068710FixUserAddressLastNameLength();

        // Set column to original size to test the migration properly, as test DB may already have VARCHAR(255)
        $this->connection->executeStatement('
            ALTER TABLE `user_address`
            MODIFY COLUMN `last_name` VARCHAR(60) COLLATE utf8mb4_unicode_ci NOT NULL
        ');

        $lastNameColumn = TableHelper::getColumnOfTable($this->connection, 'user_address', 'last_name');
        static::assertSame(Types::STRING, $lastNameColumn->type);
        static::assertSame(60, $lastNameColumn->length);

        $migration->update($this->connection);

        $lastNameColumn = TableHelper::getColumnOfTable($this->connection, 'user_address', 'last_name');
        static::assertSame(Types::STRING, $lastNameColumn->type);
        static::assertSame(255, $lastNameColumn->length);
        static::assertTrue($lastNameColumn->isNotNull);

        $migration->update($this->connection);

        $lastNameColumn = TableHelper::getColumnOfTable($this->connection, 'user_address', 'last_name');
        static::assertSame(Types::STRING, $lastNameColumn->type);
        static::assertSame(255, $lastNameColumn->length);
        static::assertTrue($lastNameColumn->isNotNull);
    }
}
