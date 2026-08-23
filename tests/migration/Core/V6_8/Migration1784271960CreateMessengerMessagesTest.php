<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784271960CreateMessengerMessages;

/**
 * @internal
 */
#[CoversClass(Migration1784271960CreateMessengerMessages::class)]
class Migration1784271960CreateMessengerMessagesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `messenger_messages`');
    }

    public function testCreatesMessengerMessagesTableIdempotently(): void
    {
        $migration = new Migration1784271960CreateMessengerMessages();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_messages', 'id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_messages', 'body'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_messages', 'headers'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_messages', 'queue_name'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_messages', 'created_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_messages', 'available_at'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'messenger_messages', 'delivered_at'));
    }
}
