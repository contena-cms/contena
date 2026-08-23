<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1785809019CreateOrganization;

/**
 * @internal
 */
#[CoversClass(Migration1785809019CreateOrganization::class)]
class Migration1785809019CreateOrganizationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesOrganizationAggregateIdempotently(): void
    {
        $migration = new Migration1785809019CreateOrganization();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['organization_unit', 'organization_unit_translation', 'organization', 'organization_translation'] as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
        }

        foreach (['id', 'technical_name', 'position', 'active', 'custom_fields', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'organization_unit', $column), $column);
        }

        foreach (['id', 'parent_id', 'organization_unit_id', 'level', 'code', 'path', 'child_count', 'position', 'active', 'custom_fields', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'organization', $column), $column);
        }

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'organization', 'fk.organization.parent_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'organization', 'fk.organization.organization_unit_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'organization_translation', 'fk.organization_translation.organization_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'organization_unit_translation', 'fk.organization_unit_translation.organization_unit_id'));
    }
}
