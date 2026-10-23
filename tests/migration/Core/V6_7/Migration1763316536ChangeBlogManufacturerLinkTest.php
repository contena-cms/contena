<?php

declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1763316536ChangeBlogManufacturerLink;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(Migration1763316536ChangeBlogManufacturerLink::class)]
class Migration1763316536ChangeBlogManufacturerLinkTest extends TestCase
{
    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();

        try {
            $this->connection->executeStatement(
                'DELETE FROM `blog_manufacturer`'
            );
        } catch (\Throwable) {
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM `blog_manufacturer`'
            );
        } catch (\Throwable) {
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763316536, (new Migration1763316536ChangeBlogManufacturerLink())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1763316536ChangeBlogManufacturerLink();
        static::assertSame(1763316536, $migration->getCreationTimestamp());
    }

    public function testMigrationUpdate(): void
    {
        // rollback
        if (!$this->existLinkColumn('blog_manufacturer')) {
            $this->connection->executeStatement(
                'ALTER TABLE `blog_manufacturer` ADD COLUMN `link` VARCHAR(255) DEFAULT NULL;'
            );
        }
        if ($this->existLinkColumn('blog_manufacturer_translation')) {
            $this->connection->executeStatement('ALTER TABLE `blog_manufacturer_translation` DROP COLUMN `link`;');
        }
        static::assertTrue($this->existLinkColumn('blog_manufacturer'));
        static::assertFalse($this->existLinkColumn('blog_manufacturer_translation'));

        // prepare blogs

        $this->createBlogManufacturer('M1', 'link1');
        $this->createBlogManufacturer('M2', 'link2');
        $this->createBlogManufacturer('M3', null);

        $expected = [
            [
                'id' => $this->ids->get('M1'),
                'name' => 'M1',
                'link' => 'link1',
            ],
            [
                'id' => $this->ids->get('M2'),
                'name' => 'M2',
                'link' => 'link2',
            ],
            [
                'id' => $this->ids->get('M3'),
                'name' => 'M3',
                'link' => null,
            ],
        ];

        static::assertSame(
            $expected,
            $this->connection->fetchAllAssociative(
                <<<'SQL'

SELECT LOWER(HEX(pm.id)) AS id, pmt.name AS name, pm.link AS link
FROM blog_manufacturer pm
LEFT JOIN blog_manufacturer_translation pmt ON pm.id = pmt.blog_manufacturer_id AND pm.version_id = pmt.blog_manufacturer_version_id
ORDER BY pmt.name ASC

SQL
            )
        );

        // test
        $migration = new Migration1763316536ChangeBlogManufacturerLink();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            $expected,
            $this->connection->fetchAllAssociative(
                <<<'SQL'

SELECT LOWER(HEX(blog_manufacturer_id)) AS id, name, link
FROM blog_manufacturer_translation
ORDER BY name ASC

SQL
            )
        );

        static::assertTrue($this->existLinkColumn('blog_manufacturer_translation'));
    }

    public function testMigrationUpdateDestructive(): void
    {
        // rollback
        if (!$this->existLinkColumn('blog_manufacturer')) {
            $this->connection->executeStatement(
                'ALTER TABLE `blog_manufacturer` ADD COLUMN `link` VARCHAR(255) DEFAULT NULL;'
            );
        }
        static::assertTrue($this->existLinkColumn('blog_manufacturer'));

        // test
        $migration = new Migration1763316536ChangeBlogManufacturerLink();
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->existLinkColumn('blog_manufacturer'));
    }

    /**
     * @param non-empty-string $table
     */
    private function existLinkColumn(string $table): bool
    {
        return TableHelper::columnExists($this->connection, $table, 'link');
    }

    private function createBlogManufacturer(string $name, ?string $link): void
    {
        $id = $this->ids->getBytes($name);
        $versionBinId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageBinId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $manufacturer = [
            'id' => $id,
            'version_id' => $versionBinId,
            'link' => $link,
            'created_at' => $now,
        ];
        $manufacturerTranslation = [
            'blog_manufacturer_id' => $id,
            'blog_manufacturer_version_id' => $versionBinId,
            'language_id' => $languageBinId,
            'name' => $name,
            'created_at' => $now,
        ];

        $this->connection->insert('blog_manufacturer', $manufacturer);
        $this->connection->insert('blog_manufacturer_translation', $manufacturerTranslation);
    }
}
