<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_6\Migration1707807389ChangeAvailableDefault;

/**
 * @internal
 */
#[CoversClass(Migration1707807389ChangeAvailableDefault::class)]
class Migration1707807389ChangeAvailableDefaultTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1707807389, (new Migration1707807389ChangeAvailableDefault())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1707807389ChangeAvailableDefault();
        $migration->update($connection);

        $column = TableHelper::getColumnOfTable($connection, BlogDefinition::ENTITY_NAME, 'available');

        static::assertSame('0', $column->defaultValue);
    }
}
