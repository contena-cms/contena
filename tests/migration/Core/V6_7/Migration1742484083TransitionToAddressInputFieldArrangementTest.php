<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1742484083TransitionToAddressInputFieldArrangement;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Migration1742484083TransitionToAddressInputFieldArrangement::class)]
class Migration1742484083TransitionToAddressInputFieldArrangementTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1742484083, (new Migration1742484083TransitionToAddressInputFieldArrangement())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->revertMigration();
        $this->prepareSystemConfig();

        $migration = new Migration1742484083TransitionToAddressInputFieldArrangement();
        $migration->update($this->connection);

        $this->assertNewConfiguration();

        $migration->update($this->connection);

        $this->assertNewConfiguration();
    }

    public function testMigrateDestructive(): void
    {
        $this->revertMigration();
        $this->prepareSystemConfig();

        $migration = new Migration1742484083TransitionToAddressInputFieldArrangement();
        $migration->update($this->connection);
        $migration->updateDestructive($this->connection);

        $this->assertNewConfiguration();

        $oldConfiguration = $this->connection->fetchAllAssociativeIndexed(
            'SELECT channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1742484083TransitionToAddressInputFieldArrangement::OLD_CONFIG_KEY]
        );

        static::assertSame([], $oldConfiguration);
    }

    public function testNotOverrideExistingConfig(): void
    {
        $this->revertMigration();
        $this->prepareSystemConfig();

        $migration = new Migration1742484083TransitionToAddressInputFieldArrangement();
        $migration->update($this->connection);

        $this->assertNewConfiguration();

        $this->connection->update('system_config', [
            'configuration_value' => '{"_value": "city-state-zip"}',
        ], [
            'configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY,
            'channel_id' => null,
        ]);

        $migration->update($this->connection);

        $newConfiguration = $this->connection->fetchAllAssociativeIndexed(
            'SELECT channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY]
        );
        foreach ($newConfiguration as $uuid => $c) {
            $newConfiguration[$uuid]['configuration_value'] = json_decode($c['configuration_value'], true);
        }

        static::assertSame([
            '' => ['configuration_value' => ['_value' => 'city-state-zip']],
            Uuid::fromHexToBytes(TestDefaults::CHANNEL) => ['configuration_value' => ['_value' => 'city-zip-state']],
        ], $newConfiguration);
    }

    private function revertMigration(): void
    {
        $this->connection->delete('system_config', ['configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY]);
    }

    private function prepareSystemConfig(): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::OLD_CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => true]),
            'channel_id' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::OLD_CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => false]),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function assertNewConfiguration(): void
    {
        $newConfiguration = $this->connection->fetchAllAssociativeIndexed(
            'SELECT channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY]
        );
        foreach ($newConfiguration as $uuid => $c) {
            $newConfiguration[$uuid]['configuration_value'] = json_decode($c['configuration_value'], true);
        }

        static::assertSame([
            '' => ['configuration_value' => ['_value' => 'zip-city-state']],
            Uuid::fromHexToBytes(TestDefaults::CHANNEL) => ['configuration_value' => ['_value' => 'city-zip-state']],
        ], $newConfiguration);
    }
}
