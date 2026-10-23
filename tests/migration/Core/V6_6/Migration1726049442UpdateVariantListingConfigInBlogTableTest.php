<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1726049442UpdateVariantListingConfigInBlogTable;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(Migration1726049442UpdateVariantListingConfigInBlogTable::class)]
class Migration1726049442UpdateVariantListingConfigInBlogTableTest extends TestCase
{
    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->ids = new IdsCollection();

        try {
            $this->connection->executeStatement(
                'DELETE FROM `blog`'
            );
        } catch (\Throwable) {
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM `blog`'
            );
        } catch (\Throwable) {
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1726049442, (new Migration1726049442UpdateVariantListingConfigInBlogTable())->getCreationTimestamp());
    }

    public function testMigrationDoesNothingWhenNoMatchingParentBlogs(): void
    {
        (new Migration1726049442UpdateVariantListingConfigInBlogTable())->update($this->connection);

        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM blog'));
    }

    public function testMigration(): void
    {
        $this->createBlogs();

        $migration = new Migration1726049442UpdateVariantListingConfigInBlogTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $expectedBlogs = [
            [
                'id' => $this->ids->get('blog-1'),
                'parent_id' => null,
                'variant_listing_config' => [
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('blog-4'),
                    'configuratorGroupConfig' => [],
                ],
            ],
            [
                'id' => $this->ids->get('blog-2'),
                'parent_id' => null,
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->get('blog-3'),
                'parent_id' => null,
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->get('blog-4'),
                'parent_id' => $this->ids->get('blog-1'),
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->get('blog-5'),
                'parent_id' => $this->ids->get('blog-3'),
                'variant_listing_config' => null,
            ],
        ];

        $blogs = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, LOWER(HEX(parent_id)) as parent_id, variant_listing_config FROM blog ORDER BY blog_number ASC'
        );

        foreach ($blogs as &$blog) {
            $blog['variant_listing_config'] = $blog['variant_listing_config'] ? json_decode($blog['variant_listing_config'], true) : null;
        }

        static::assertSame($expectedBlogs, $blogs);
    }

    public function testMigrationWidensDisplayGroupColumnBeforeWritingSha256(): void
    {
        $this->connection->executeStatement('ALTER TABLE `blog` MODIFY `display_group` VARCHAR(50) NULL');

        $this->createBlogs();

        (new Migration1726049442UpdateVariantListingConfigInBlogTable())->update($this->connection);

        $column = $this->connection->fetchAssociative('SHOW COLUMNS FROM `blog` LIKE :column', [
            'column' => 'display_group',
        ]);
        static::assertIsArray($column);
        static::assertSame('varchar(64)', strtolower((string) $column['Type']));

        $displayGroup = $this->connection->fetchOne(
            'SELECT display_group FROM blog WHERE blog_number = :number',
            ['number' => 'blog 5']
        );
        static::assertIsString($displayGroup);
        static::assertSame(64, \strlen($displayGroup));
    }

    private function createBlogs(): void
    {
        $blogs = [
            [
                'id' => $this->ids->getBytes('blog-1'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => null,
                'blog_number' => 'blog 1',
                'stock' => 10,
                'variant_listing_config' => json_encode([
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('blog-4'),
                    'configuratorGroupConfig' => [],
                ]),
            ],
            [
                'id' => $this->ids->getBytes('blog-2'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => null,
                'blog_number' => 'blog 2',
                'stock' => 10,
                'variant_listing_config' => json_encode([
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('blog-6'),
                    'configuratorGroupConfig' => [],
                ]),
            ],
            [
                'id' => $this->ids->getBytes('blog-3'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => null,
                'blog_number' => 'blog 3',
                'stock' => 10,
                'variant_listing_config' => json_encode([
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('blog-4'),
                    'configuratorGroupConfig' => [],
                ]),
            ],
            [
                'id' => $this->ids->getBytes('blog-4'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => $this->ids->getBytes('blog-1'),
                'blog_number' => 'blog 4',
                'stock' => 10,
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->getBytes('blog-5'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => $this->ids->getBytes('blog-3'),
                'blog_number' => 'blog 5',
                'stock' => 10,
                'variant_listing_config' => null,
            ],
        ];

        foreach ($blogs as $blog) {
            $this->connection->insert('blog', $blog);
        }
    }
}
