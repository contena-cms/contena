<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1792407860BackfillOrderLineItemBlogTypePayload;

/**
 * @internal
 */
#[CoversClass(Migration1792407860BackfillOrderLineItemBlogTypePayload::class)]
class Migration1792407860BackfillOrderLineItemBlogTypePayloadTest extends TestCase
{
    use MigrationTestTrait;

    private static bool $statesColumnAdded = false;

    private Connection $connection;

    private string $orderId;

    public static function setUpBeforeClass(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if (TableHelper::columnExists($connection, 'order_line_item', 'states')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `order_line_item` ADD COLUMN `states` JSON NULL');
        self::$statesColumnAdded = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$statesColumnAdded) {
            return;
        }

        KernelLifecycleManager::getConnection()->executeStatement('ALTER TABLE `order_line_item` DROP COLUMN `states`');
        self::$statesColumnAdded = false;
    }

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->orderId = Uuid::randomBytes();

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792407860, (new Migration1792407860BackfillOrderLineItemBlogTypePayload())->getCreationTimestamp());
    }

    public function testFillsBlogTypeOfEveryLineItemWithoutOne(): void
    {
        $this->insertLineItems(1500, type: 'blog', states: ['is-download']);
        $this->insertLineItems(1500, type: 'blog', states: ['is-physical']);
        $this->insertLineItems(10, type: 'custom', states: ['is-download']);
        $this->insertLineItems(10, type: 'custom', states: ['is-physical']);

        $migration = new Migration1792407860BackfillOrderLineItemBlogTypePayload();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame([
            ['type' => 'custom', 'state' => 'is-download', 'blogType' => 'digital', 'count' => 10],
            ['type' => 'custom', 'state' => 'is-physical', 'blogType' => 'physical', 'count' => 10],
            ['type' => 'blog', 'state' => 'is-download', 'blogType' => 'digital', 'count' => 1500],
            ['type' => 'blog', 'state' => 'is-physical', 'blogType' => 'physical', 'count' => 1500],
        ], $this->fetchBlogTypes());
        static::assertSame(3020, $this->countLineItemsWithBlogNumber());
    }

    public function testKeepsBlogTypeThatIsAlreadySet(): void
    {
        $this->insertLineItems(1, type: 'blog', states: ['is-download'], payload: ['blogType' => 'digital']);
        $this->insertLineItems(1, type: 'blog', states: ['is-physical'], payload: ['blogType' => 'subscription']);

        (new Migration1792407860BackfillOrderLineItemBlogTypePayload())->update($this->connection);

        static::assertSame([
            ['type' => 'blog', 'state' => 'is-download', 'blogType' => 'digital', 'count' => 1],
            ['type' => 'blog', 'state' => 'is-physical', 'blogType' => 'subscription', 'count' => 1],
        ], $this->fetchBlogTypes());
    }

    public function testLeavesLineItemsWithoutBlogStateUntouched(): void
    {
        $this->insertLineItems(1, type: 'promotion', states: ['is-physical']);
        $this->insertLineItems(1, type: 'blog', states: []);
        $this->insertLineItems(1, type: 'blog', states: null);

        (new Migration1792407860BackfillOrderLineItemBlogTypePayload())->update($this->connection);

        static::assertSame([
            ['type' => 'blog', 'state' => null, 'blogType' => null, 'count' => 2],
            ['type' => 'promotion', 'state' => 'is-physical', 'blogType' => null, 'count' => 1],
        ], $this->fetchBlogTypes());
    }

    public function testFillsEveryVersionOfALineItem(): void
    {
        $lineItemId = Uuid::randomBytes();

        $queue = new MultiInsertQueryQueue($this->connection);
        $queue->addInsert('order_line_item', $this->lineItem($lineItemId, Defaults::LIVE_VERSION, type: 'blog', states: ['is-download']));
        $queue->addInsert('order_line_item', $this->lineItem($lineItemId, Uuid::randomHex(), type: 'blog', states: ['is-download']));
        $queue->execute();

        (new Migration1792407860BackfillOrderLineItemBlogTypePayload())->update($this->connection);

        static::assertSame([
            ['type' => 'blog', 'state' => 'is-download', 'blogType' => 'digital', 'count' => 2],
        ], $this->fetchBlogTypes());
    }

    /**
     * @param list<string>|null $states
     * @param array<string, string> $payload
     */
    private function insertLineItems(int $count, string $type, ?array $states, array $payload = ['blogNumber' => 'SW10000']): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 500);

        for ($i = 0; $i < $count; ++$i) {
            $queue->addInsert('order_line_item', $this->lineItem(Uuid::randomBytes(), Defaults::LIVE_VERSION, $type, $states, $payload));
        }

        $queue->execute();
    }

    /**
     * @param list<string>|null $states
     * @param array<string, string> $payload
     *
     * @return array<string, mixed>
     */
    private function lineItem(string $id, string $versionId, string $type, ?array $states, array $payload = ['blogNumber' => 'SW10000']): array
    {
        return [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes($versionId),
            'order_id' => $this->orderId,
            'order_version_id' => Uuid::fromHexToBytes($versionId),
            'identifier' => Uuid::fromBytesToHex($id),
            'label' => 'Line item',
            'quantity' => 1,
            'type' => $type,
            'payload' => Json::encode($payload),
            'states' => $states === null ? null : Json::encode($states),
            'price' => Json::encode(['unitPrice' => 10.0, 'totalPrice' => 10.0, 'quantity' => 1, 'calculatedTaxes' => [], 'taxRules' => []]),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    /**
     * @return list<array{type: string, state: string|null, blogType: string|null, count: int}>
     */
    private function fetchBlogTypes(): array
    {
        /** @var list<array{type: string, state: string|null, blogType: string|null, count: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `type`,
                JSON_UNQUOTE(JSON_EXTRACT(`states`, \'$[0]\')) AS `state`,
                JSON_UNQUOTE(JSON_EXTRACT(`payload`, \'$.blogType\')) AS `blogType`,
                COUNT(*) AS `count`
            FROM `order_line_item`
            WHERE `order_id` = :orderId
            GROUP BY `type`, `state`, `blogType`
            ORDER BY `type`, `state`, `blogType`',
            ['orderId' => $this->orderId]
        );

        return array_map(static fn (array $row): array => [...$row, 'count' => (int) $row['count']], $rows);
    }

    private function countLineItemsWithBlogNumber(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `order_line_item` WHERE `order_id` = :orderId AND JSON_CONTAINS_PATH(`payload`, \'one\', \'$.blogNumber\')',
            ['orderId' => $this->orderId]
        );
    }
}
