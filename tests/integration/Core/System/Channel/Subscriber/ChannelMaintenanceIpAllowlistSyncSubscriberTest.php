<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @internal
 */
class ChannelMaintenanceIpAllowlistSyncSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->connection = static::getContainer()->get(Connection::class);
        $this->repository = static::getContainer()->get('channel.repository');
    }

    public function testWritingNewAllowlistViaApiSyncsDeprecatedWhitelist(): void
    {
        $id = Uuid::randomHex();
        $this->createChannel([
            'id' => $id,
            'maintenanceIpAllowlist' => ['127.0.0.1', '::1'],
        ]);

        $this->assertColumns($id, ['127.0.0.1', '::1'], ['127.0.0.1', '::1']);
    }

    public function testWritingDeprecatedWhitelistViaApiSyncsNewAllowlist(): void
    {
        $id = Uuid::randomHex();
        $this->createChannel([
            'id' => $id,
            'maintenanceIpWhitelist' => ['10.0.0.1'],
        ]);

        $this->assertColumns($id, ['10.0.0.1'], ['10.0.0.1']);
    }

    public function testNewAllowlistWinsWhenBothAreWrittenViaApi(): void
    {
        $id = Uuid::randomHex();
        $this->createChannel([
            'id' => $id,
            'maintenanceIpAllowlist' => ['127.0.0.1'],
            'maintenanceIpWhitelist' => ['10.0.0.1'],
        ]);

        $this->assertColumns($id, ['127.0.0.1'], ['127.0.0.1']);
    }

    public function testUpdatingNewAllowlistViaApiSyncsDeprecatedWhitelist(): void
    {
        $id = Uuid::randomHex();
        $this->createChannel([
            'id' => $id,
            'maintenanceIpAllowlist' => ['127.0.0.1'],
        ]);

        $this->repository->update([[
            'id' => $id,
            'maintenanceIpAllowlist' => ['192.168.0.1'],
        ]], Context::createDefaultContext());

        $this->assertColumns($id, ['192.168.0.1'], ['192.168.0.1']);
    }

    /**
     * @param list<string> $expectedAllowlist
     * @param list<string> $expectedWhitelist
     */
    private function assertColumns(string $id, array $expectedAllowlist, array $expectedWhitelist): void
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT maintenance_ip_allowlist, maintenance_ip_whitelist
                FROM channel
                WHERE id = :id
            SQL,
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($row);
        static::assertSame(
            $expectedAllowlist,
            json_decode((string) $row['maintenance_ip_allowlist'], true, 512, \JSON_THROW_ON_ERROR)
        );
        static::assertSame(
            $expectedWhitelist,
            json_decode((string) $row['maintenance_ip_whitelist'], true, 512, \JSON_THROW_ON_ERROR)
        );
    }
}
