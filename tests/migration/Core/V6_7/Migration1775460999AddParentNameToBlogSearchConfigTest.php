<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_7\Migration1775460999AddParentNameToBlogSearchConfig;

/**
 * @internal
 */
#[CoversClass(Migration1775460999AddParentNameToBlogSearchConfig::class)]
class Migration1775460999AddParentNameToBlogSearchConfigTest extends TestCase
{
    private Connection $connection;

    private Migration1775460999AddParentNameToBlogSearchConfig $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1775460999AddParentNameToBlogSearchConfig();

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1775460999, $this->migration->getCreationTimestamp());
    }

    public function testAddParentNameToBlogSearchConfig(): void
    {
        $nameConfig = $this->connection->fetchAssociative(
            'SELECT blog_search_config_id, tokenize, searchable, ranking
            FROM blog_search_config_field
            WHERE field = :field
            LIMIT 1',
            ['field' => 'name']
        );

        if ($nameConfig === false) {
            static::markTestSkipped('No blog search config field for name found');
        }

        $this->connection->executeStatement(
            'DELETE FROM blog_search_config_field WHERE blog_search_config_id = :configId AND field = :field',
            [
                'configId' => $nameConfig['blog_search_config_id'],
                'field' => 'parent.name',
            ]
        );

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $parentConfig = $this->connection->fetchAssociative(
            'SELECT tokenize, searchable, ranking
            FROM blog_search_config_field
            WHERE blog_search_config_id = :configId AND field = :field',
            [
                'configId' => $nameConfig['blog_search_config_id'],
                'field' => 'parent.name',
            ]
        );

        static::assertNotFalse($parentConfig);
        static::assertSame((string) $nameConfig['tokenize'], (string) $parentConfig['tokenize']);
        static::assertSame('0', (string) $parentConfig['searchable']);
        static::assertSame((string) round((float) $nameConfig['ranking'] * 0.8), (string) $parentConfig['ranking']);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM blog_search_config_field WHERE blog_search_config_id = :configId AND field = :field',
            [
                'configId' => $nameConfig['blog_search_config_id'],
                'field' => 'parent.name',
            ]
        );

        static::assertSame('1', (string) $count);
    }
}
