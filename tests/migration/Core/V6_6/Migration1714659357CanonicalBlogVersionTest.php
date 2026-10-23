<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_6\Migration1714659357CanonicalBlogVersion;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Migration1714659357CanonicalBlogVersion::class)]
class Migration1714659357CanonicalBlogVersionTest extends TestCase
{
    use KernelTestBehaviour;

    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);

        $version = strtolower($this->connection->getServerVersion());

        if (!str_contains($version, 'mariadb') && version_compare($version, '8.4.0', '>=')) {
            static::markTestSkipped('Test is only relevant for MariaDB or MySQL < 8.4.0');
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1714659357, (new Migration1714659357CanonicalBlogVersion())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('ALTER TABLE `blog` DROP FOREIGN KEY `fk.blog.canonical_product_id`');
        $this->connection->executeStatement('ALTER TABLE `blog` DROP INDEX `fk.blog.canonical_product_id`');

        $this->connection->executeStatement('ALTER TABLE `blog` DROP COLUMN `canonical_product_version_id`');
        $this->connection->executeStatement('ALTER TABLE `blog`  DROP COLUMN `canonical_product_id`');

        $this->connection->executeStatement('ALTER TABLE `blog` ADD COLUMN `canonical_product_id` BINARY(16) NULL');
        $this->connection->executeStatement('ALTER TABLE `blog` ADD CONSTRAINT `fk.blog.canonical_product_id` FOREIGN KEY (`canonical_product_id`) REFERENCES `blog` (`id`) ON DELETE SET NULL');

        $m = new Migration1714659357CanonicalBlogVersion();
        $m->update($this->connection);
        $m->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'blog', 'canonical_product_version_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'blog', 'canonical_product_id'));

        static::assertTrue(TableHelper::indexExists($this->connection, 'blog', 'fk.blog.canonical_product_id'));
    }
}
