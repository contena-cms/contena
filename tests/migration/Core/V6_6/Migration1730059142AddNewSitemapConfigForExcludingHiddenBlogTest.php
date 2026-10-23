<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_6\Migration1730059142AddNewSitemapConfigForExcludingHiddenBlogs;
use Contena\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1730059142AddNewSitemapConfigForExcludingHiddenBlogs::class)]
class Migration1730059142AddNewSitemapConfigForExcludingHiddenBlogsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => 'core.sitemap.excludeLinkedBlogs']);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1730059142, (new Migration1730059142AddNewSitemapConfigForExcludingHiddenBlogs())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertCount(0, $this->getConfig());

        $migration = new Migration1730059142AddNewSitemapConfigForExcludingHiddenBlogs();
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.sitemap.excludeLinkedBlogs', $record['configuration_key']);
        static::assertSame('{"_value": false}', $record['configuration_value']);

        $this->connection->update('system_config', ['configuration_value' => '{"_value": true}'], ['configuration_key' => 'core.sitemap.excludeLinkedBlogs']);

        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.sitemap.excludeLinkedBlogs', $record['configuration_key']);
        static::assertSame('{"_value": true}', $record['configuration_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM system_config WHERE configuration_key = \'core.sitemap.excludeLinkedBlogs\''
        ) ?: [];
    }
}
