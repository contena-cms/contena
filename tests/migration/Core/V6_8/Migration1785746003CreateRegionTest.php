<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1785746003CreateRegion;

/**
 * @internal
 */
#[CoversClass(Migration1785746003CreateRegion::class)]
class Migration1785746003CreateRegionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesHierarchicalRegionTableIdempotently(): void
    {
        $migration = new Migration1785746003CreateRegion();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach ([
            'id',
            'country_id',
            'parent_id',
            'level',
            'type',
            'code',
            'path',
            'child_count',
            'position',
            'active',
            'custom_fields',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'region', $column), $column);
        }

        foreach ([
            'idx.region.parent_id',
            'idx.region.country_level_active',
            'idx.region.country_type_active',
            'idx.region.country_code',
        ] as $index) {
            static::assertTrue(TableHelper::indexExists($this->connection, 'region', $index), $index);
        }

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'region', 'fk.region.country_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'region', 'fk.region.parent_id'));

        foreach ([
            'region_id',
            'language_id',
            'name',
            'short_name',
            'custom_fields',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'region_translation', $column), $column);
        }

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'region_translation', 'fk.region_translation.region_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'region_translation', 'fk.region_translation.language_id'));
    }
}
