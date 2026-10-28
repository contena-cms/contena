<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1702982372FixProductCrossSellingSortByPrice;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1702982372FixProductCrossSellingSortByPrice::class)]
class Migration1702982372FixProductCrossSellingSortByPriceTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1702982372, (new Migration1702982372FixProductCrossSellingSortByPrice())->getCreationTimestamp());
    }

    public function testMigrationChangesSortBy(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $productId = Uuid::randomBytes();
        $connection->insert('product', [
            'id' => $productId,
            'stock' => 1,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        $crossSellingId = Uuid::randomBytes();
        $connection->insert('product_cross_selling', [
            'id' => $crossSellingId,
            'product_id' => $productId,
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'position' => 0,
            'type' => 'productStream',
            'sort_by' => 'price',
            'sort_direction' => FieldSorting::ASCENDING,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1702982372FixProductCrossSellingSortByPrice();
        $migration->update($connection);

        $sortBy = $connection->executeQuery('
        SELECT `sort_by` from `product_cross_selling` WHERE `id` = :id
        ', ['id' => $crossSellingId])->fetchOne();

        static::assertSame('cheapestPrice', $sortBy);
    }
}
