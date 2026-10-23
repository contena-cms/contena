<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1788182786AddDefaultCurrenciesToChannels;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1788182786AddDefaultCurrenciesToChannels::class)]
class Migration1788182786AddDefaultCurrenciesToChannelsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788182786, (new Migration1788182786AddDefaultCurrenciesToChannels())->getCreationTimestamp());
    }

    public function testMigrationAssignsMissingDefaultCurrency(): void
    {
        $channelId = Uuid::fromHexToBytes(TestDefaults::CHANNEL);
        $currencyId = $this->connection->fetchOne(
            'SELECT `currency_id` FROM `channel` WHERE `id` = :id',
            ['id' => $channelId]
        );
        static::assertIsString($currencyId);

        $this->connection->delete('channel_currency', [
            'channel_id' => $channelId,
            'currency_id' => $currencyId,
        ]);

        $migration = new Migration1788182786AddDefaultCurrenciesToChannels();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `channel_currency` WHERE `channel_id` = :channelId AND `currency_id` = :currencyId',
            [
                'channelId' => $channelId,
                'currencyId' => $currencyId,
            ]
        ));
    }
}
