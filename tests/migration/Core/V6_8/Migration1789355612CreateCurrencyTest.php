<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1789355612CreateCurrency;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Migration1789355612CreateCurrency::class)]
class Migration1789355612CreateCurrencyTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `currency_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `currency`');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testCreatesAndSeedsCurrencySchemaIdempotently(): void
    {
        $migration = new Migration1789355612CreateCurrency();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'currency'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'currency_translation'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'currency', 'uniq.currency.iso_code'));
        static::assertSame(10, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `currency`'));
        static::assertSame(
            ['AUD', 'CAD', 'CHF', 'CNY', 'EUR', 'GBP', 'HKD', 'JPY', 'SGD', 'USD'],
            $this->connection->fetchFirstColumn('SELECT `iso_code` FROM `currency` ORDER BY `iso_code`'),
        );
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT `decimal_precision` FROM `currency` WHERE `iso_code` = :isoCode', ['isoCode' => 'JPY']));
        static::assertSame(
            Defaults::CURRENCY,
            strtolower((string) $this->connection->fetchOne('SELECT HEX(`id`) FROM `currency` WHERE `iso_code` = :isoCode', ['isoCode' => Defaults::DEFAULT_CURRENCY_CODE])),
        );
    }
}
