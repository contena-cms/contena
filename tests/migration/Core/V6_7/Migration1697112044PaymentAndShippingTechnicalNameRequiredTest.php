<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Payment\PaymentMethodDefinition;
use Contena\Core\Checkout\Shipping\ShippingMethodDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1697112044PaymentAndShippingTechnicalNameRequired;

/**
 * @internal
 */
#[CoversClass(Migration1697112044PaymentAndShippingTechnicalNameRequired::class)]
class Migration1697112044PaymentAndShippingTechnicalNameRequiredTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1697112044, (new Migration1697112044PaymentAndShippingTechnicalNameRequired())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $paymentMethodTechnicalNameColumn = TableHelper::getColumnOfTable($this->connection, PaymentMethodDefinition::ENTITY_NAME, 'technical_name');
        static::assertTrue($paymentMethodTechnicalNameColumn->isNotNull);

        $shippingMethodTechnicalNameColumn = TableHelper::getColumnOfTable($this->connection, ShippingMethodDefinition::ENTITY_NAME, 'technical_name');
        static::assertTrue($shippingMethodTechnicalNameColumn->isNotNull);
    }

    private function migrate(): void
    {
        (new Migration1697112044PaymentAndShippingTechnicalNameRequired())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `payment_method` MODIFY COLUMN `technical_name` VARCHAR(255) NULL');
        $this->connection->executeStatement('ALTER TABLE `shipping_method` MODIFY COLUMN `technical_name` VARCHAR(255) NULL');
    }
}
