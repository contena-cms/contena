<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1774359918BlogPriceQuantityRangeMinValues;

/**
 * @internal
 */
#[CoversClass(Migration1774359918BlogPriceQuantityRangeMinValues::class)]
class Migration1774359918BlogPriceQuantityRangeMinValuesTest extends TestCase
{
    private Connection $connection;

    private string $blogId;

    private string $ruleId;

    private string $rule2Id;

    private string $blogPriceId;

    private string $blogPrice2Id;

    private string $blogPrice3Id;

    private string $versionId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->blogId = Uuid::randomBytes();
        $this->ruleId = Uuid::randomBytes();
        $this->rule2Id = Uuid::randomBytes();
        $this->blogPriceId = Uuid::randomBytes();
        $this->blogPrice2Id = Uuid::randomBytes();
        $this->blogPrice3Id = Uuid::randomBytes();
        $this->versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
    }

    protected function tearDown(): void
    {
        $this->connection->delete('blog', ['id' => $this->blogId]);
        $this->connection->delete('rule', ['id' => $this->ruleId]);
        $this->connection->delete('rule', ['id' => $this->rule2Id]);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1774359918, (new Migration1774359918BlogPriceQuantityRangeMinValues())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1774359918BlogPriceQuantityRangeMinValues();

        static::assertSame(1774359918, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $queue = new MultiInsertQueryQueue($this->connection);

        $queue->addInsert('blog', [
            'id' => $this->blogId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('rule', [
            'id' => $this->ruleId,
            'name' => 'Price rule 1',
            'priority' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('rule', [
            'id' => $this->rule2Id,
            'name' => 'Price rule 2',
            'priority' => 2,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('blog_price', [
            'id' => $this->blogPriceId,
            'version_id' => $this->versionId,
            'rule_id' => $this->ruleId,
            'blog_id' => $this->blogId,
            'blog_version_id' => $this->versionId,
            'price' => json_encode([
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 9,
                    'linked' => false,
                ],
            ], \JSON_THROW_ON_ERROR),
            'quantity_start' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('blog_price', [
            'id' => $this->blogPrice2Id,
            'version_id' => $this->versionId,
            'rule_id' => $this->rule2Id,
            'blog_id' => $this->blogId,
            'blog_version_id' => $this->versionId,
            'price' => json_encode([
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 20,
                    'net' => 18,
                    'linked' => false,
                ],
            ], \JSON_THROW_ON_ERROR),
            'quantity_start' => -1,
            'quantity_end' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->addInsert('blog_price', [
            'id' => $this->blogPrice3Id,
            'version_id' => $this->versionId,
            'rule_id' => $this->rule2Id,
            'blog_id' => $this->blogId,
            'blog_version_id' => $this->versionId,
            'price' => json_encode([
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 30,
                    'net' => 27,
                    'linked' => false,
                ],
            ], \JSON_THROW_ON_ERROR),
            'quantity_start' => 2,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $queue->execute();

        $migration = new Migration1774359918BlogPriceQuantityRangeMinValues();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $prices = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(`id`)), `quantity_start`, `quantity_end` FROM `blog_price` WHERE `id` IN (:ids)',
            ['ids' => [$this->blogPriceId, $this->blogPrice2Id, $this->blogPrice3Id]],
            ['ids' => ArrayParameterType::BINARY]
        );

        $price = $prices[Uuid::fromBytesToHex($this->blogPriceId)];

        static::assertIsArray($price);
        static::assertSame('1', $price['quantity_start']);
        static::assertNull($price['quantity_end']);

        $price2 = $prices[Uuid::fromBytesToHex($this->blogPrice2Id)];

        static::assertIsArray($price2);
        static::assertSame('1', $price2['quantity_start']);
        static::assertSame('1', $price2['quantity_end']);

        $price3 = $prices[Uuid::fromBytesToHex($this->blogPrice3Id)];

        static::assertIsArray($price3);
        static::assertSame('2', $price3['quantity_start']);
        static::assertNull($price3['quantity_end']);
    }
}
