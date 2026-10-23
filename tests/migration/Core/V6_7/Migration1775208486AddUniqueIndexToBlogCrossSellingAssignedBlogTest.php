<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1775208486AddUniqueIndexToBlogCrossSellingAssignedBlogs;

/**
 * @internal
 */
#[CoversClass(Migration1775208486AddUniqueIndexToBlogCrossSellingAssignedBlogs::class)]
class Migration1775208486AddUniqueIndexToBlogCrossSellingAssignedBlogsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->rollback();
    }

    protected function tearDown(): void
    {
        $this->rollback();

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1775208486,
            (new Migration1775208486AddUniqueIndexToBlogCrossSellingAssignedBlogs())->getCreationTimestamp()
        );
    }

    public function testMigrationRemovesExistingDuplicateAssignedBlogsAndAddsUniqueIndex(): void
    {
        $mainBlogId = Uuid::randomBytes();
        $assignedBlogId = Uuid::randomBytes();
        $crossSellingId = Uuid::randomBytes();
        $olderAssignedId = Uuid::randomBytes();
        $newerAssignedId = Uuid::randomBytes();
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $queue = new MultiInsertQueryQueue($this->connection);

        $queue->addInsert('blog', [
            'id' => $mainBlogId,
            'stock' => 1,
            'version_id' => $liveVersionId,
        ]);

        $queue->addInsert('blog', [
            'id' => $assignedBlogId,
            'stock' => 1,
            'version_id' => $liveVersionId,
        ]);

        $queue->addInsert('blog_cross_selling', [
            'id' => $crossSellingId,
            'blog_id' => $mainBlogId,
            'blog_version_id' => $liveVersionId,
            'position' => 0,
            'sort_by' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sort_direction' => FieldSorting::ASCENDING,
            'type' => BlogCrossSellingDefinition::TYPE_BLOG_LIST,
            'active' => 1,
            'created_at' => '2025-01-01 00:00:00.000',
        ]);

        $queue->addInsert('blog_cross_selling_assigned_products', [
            'id' => $olderAssignedId,
            'cross_selling_id' => $crossSellingId,
            'blog_id' => $assignedBlogId,
            'blog_version_id' => $liveVersionId,
            'position' => 1,
            'created_at' => '2025-01-01 00:00:00.000',
        ]);

        $queue->addInsert('blog_cross_selling_assigned_products', [
            'id' => $newerAssignedId,
            'cross_selling_id' => $crossSellingId,
            'blog_id' => $assignedBlogId,
            'blog_version_id' => $liveVersionId,
            'position' => 2,
            'created_at' => '2025-06-01 00:00:00.000',
        ]);

        $queue->execute();

        $countBefore = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM blog_cross_selling_assigned_products WHERE cross_selling_id = :crossSellingId AND blog_id = :blogId',
            [
                'crossSellingId' => $crossSellingId,
                'blogId' => $assignedBlogId,
            ]
        );
        static::assertSame(2, $countBefore);

        $migration = new Migration1775208486AddUniqueIndexToBlogCrossSellingAssignedBlogs();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $countAfter = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM blog_cross_selling_assigned_products WHERE cross_selling_id = :crossSellingId AND blog_id = :blogId',
            [
                'crossSellingId' => $crossSellingId,
                'blogId' => $assignedBlogId,
            ]
        );
        static::assertSame(1, $countAfter);

        $remainingId = $this->connection->fetchOne(
            'SELECT id FROM blog_cross_selling_assigned_products WHERE cross_selling_id = :crossSellingId AND blog_id = :blogId',
            [
                'crossSellingId' => $crossSellingId,
                'blogId' => $assignedBlogId,
            ]
        );
        static::assertSame($newerAssignedId, $remainingId);

        static::assertTrue(
            TableHelper::indexExists(
                $this->connection,
                'blog_cross_selling_assigned_products',
                'uniq.cross_selling_id__product_id__product_version_id'
            )
        );

        $index = TableHelper::getIndexOfTable(
            $this->connection,
            'blog_cross_selling_assigned_products',
            'uniq.cross_selling_id__product_id__product_version_id'
        );
        static::assertSame(IndexType::UNIQUE->name, $index->type);
    }

    private function rollback(): void
    {
        if (TableHelper::indexExists(
            $this->connection,
            'blog_cross_selling_assigned_products',
            'uniq.cross_selling_id__product_id__product_version_id'
        )) {
            $this->connection->executeStatement(
                'ALTER TABLE `blog_cross_selling_assigned_products` DROP INDEX `uniq.cross_selling_id__product_id__product_version_id`'
            );
        }
    }
}
