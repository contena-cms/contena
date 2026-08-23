<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784277875CreateInvalidationTags;

/**
 * @internal
 */
#[CoversClass(Migration1784277875CreateInvalidationTags::class)]
class Migration1784277875CreateInvalidationTagsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `invalidation_tags`');
    }

    public function testCreatesInvalidationTagsTableIdempotently(): void
    {
        $migration = new Migration1784277875CreateInvalidationTags();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'invalidation_tags', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'invalidation_tags', 'tag'));
    }
}
