<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1786447612FixChannelAnalyticsConstraint;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @internal
 */
#[CoversClass(Migration1786447612FixChannelAnalyticsConstraint::class)]
class Migration1786447612FixChannelAnalyticsConstraintTest extends TestCase
{
    private const FOREIGN_KEY_NAME = 'fk.channel.analytics_id';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1786447612, (new Migration1786447612FixChannelAnalyticsConstraint())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->resetAnalyticsForeignKey();

        $foreignKeyBefore = TableHelper::getForeignKeyOfTable(
            $this->connection,
            ChannelDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::CASCADE->value, $foreignKeyBefore->onDeleteAction);

        $migration = new Migration1786447612FixChannelAnalyticsConstraint();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $foreignKeyAfter = TableHelper::getForeignKeyOfTable(
            $this->connection,
            ChannelDefinition::ENTITY_NAME,
            self::FOREIGN_KEY_NAME
        );
        static::assertSame(ReferentialAction::SET_NULL->value, $foreignKeyAfter->onDeleteAction);
    }

    private function resetAnalyticsForeignKey(): void
    {
        try {
            $this->connection->executeStatement(\sprintf('
                ALTER TABLE `channel`
                DROP FOREIGN KEY `%s`;
            ', self::FOREIGN_KEY_NAME));
        } catch (\Throwable) {
        }

        $this->connection->executeStatement(\sprintf('
            ALTER TABLE `channel`
            ADD CONSTRAINT `%s`
            FOREIGN KEY (`analytics_id`)
            REFERENCES `channel_analytics` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE;
        ', self::FOREIGN_KEY_NAME));
    }
}
