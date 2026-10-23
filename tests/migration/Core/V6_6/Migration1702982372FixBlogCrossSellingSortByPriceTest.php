<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1702982372FixBlogCrossSellingSortByPrice;

/**
 * @internal
 */
#[CoversClass(Migration1702982372FixBlogCrossSellingSortByPrice::class)]
class Migration1702982372FixBlogCrossSellingSortByPriceTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1702982372, (new Migration1702982372FixBlogCrossSellingSortByPrice())->getCreationTimestamp());
    }

    public function testMigrationChangesSortBy(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $blogId = Uuid::randomBytes();
        $connection->insert('blog', [
            'id' => $blogId,
            'stock' => 1,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        $crossSellingId = Uuid::randomBytes();
        $connection->insert('blog_cross_selling', [
            'id' => $crossSellingId,
            'blog_id' => $blogId,
            'blog_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'position' => 0,
            'type' => 'contentStream',
            'sort_by' => 'price',
            'sort_direction' => FieldSorting::ASCENDING,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1702982372FixBlogCrossSellingSortByPrice();
        $migration->update($connection);

        $sortBy = $connection->executeQuery('
        SELECT `sort_by` from `blog_cross_selling` WHERE `id` = :id
        ', ['id' => $crossSellingId])->fetchOne();

        static::assertSame('cheapestPrice', $sortBy);
    }
}
