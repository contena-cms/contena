<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_6\Migration1696515133AddCheckoutGatewayUrl;
use Contena\Core\Migration\V6_7\Migration1743151679AddContextGatewayUrl;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1696515133AddCheckoutGatewayUrl::class)]
class Migration1743151679AddContextGatewayUrlTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1743151679, (new Migration1743151679AddContextGatewayUrl())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('ALTER TABLE `app` DROP COLUMN `checkout_gateway_url`');

        $migration = new Migration1696515133AddCheckoutGatewayUrl();

        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(TableHelper::columnExists($connection, 'app', 'checkout_gateway_url'));
    }
}
