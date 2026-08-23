<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784207055AddUserGender;

/**
 * @internal
 */
#[CoversClass(Migration1784207055AddUserGender::class)]
class Migration1784207055AddUserGenderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testAddsNullableUserGenderCodeIdempotently(): void
    {
        $migration = new Migration1784207055AddUserGender();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'user', 'gender');

        static::assertSame('string', $column->type);
        static::assertSame(255, $column->length);
        static::assertFalse($column->isNotNull);
        static::assertFalse(TableHelper::columnExists($this->connection, 'user', 'gender_id'));
    }
}
