<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1720610755RemoveDefaultPaymentMethodFromUser;

/**
 * @internal
 */
#[CoversClass(Migration1720610755RemoveDefaultPaymentMethodFromUser::class)]
class Migration1720610755RemoveDefaultPaymentMethodFromUserTest extends TestCase
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1720610755, (new Migration1720610755RemoveDefaultPaymentMethodFromUser())->getCreationTimestamp());
    }

    public function testUpdateMakesColumnNullable(): void
    {
        if (!TableHelper::columnExists($this->connection, 'user', 'default_payment_method_id')) {
            $this->addColumn();
        }

        $migration = new Migration1720610755RemoveDefaultPaymentMethodFromUser();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'user', 'default_payment_method_id');
        static::assertFalse($column->isNotNull);
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $exists = TableHelper::columnExists($this->connection, 'user', 'default_payment_method_id');

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1720610755RemoveDefaultPaymentMethodFromUser();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'user', 'default_payment_method_id'));

        if ($exists) {
            $this->addColumn();
        }
    }

    private function addColumn(): void
    {
        $this->connection
            ->executeStatement(
                'ALTER TABLE `user`
                    ADD COLUMN `default_payment_method_id` BINARY(16) NOT NULL DEFAULT :defaultPaymentMethodId,
                    ADD CONSTRAINT `fk.user.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
                ['defaultPaymentMethodId' => Uuid::fromHexToBytes($this->getValidPaymentMethodId())]
            );
    }
}
