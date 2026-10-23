<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1773329152AddAgenticAiChannelType;

/**
 * @internal
 */
#[CoversClass(Migration1773329152AddAgenticAiChannelType::class)]
class Migration1773329152AddAgenticAiChannelTypeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773329152, (new Migration1773329152AddAgenticAiChannelType())->getCreationTimestamp());
    }

    public function testUpdateIsIdempotent(): void
    {
        $migration = new Migration1773329152AddAgenticAiChannelType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `channel_type` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE)]
            )
        );
    }

    public function testUpdateInsertsTypeAndTranslations(): void
    {
        $id = Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE);

        $this->connection->executeStatement(
            'DELETE FROM `channel` WHERE `type_id` = :id',
            ['id' => $id]
        );
        $this->connection->executeStatement(
            'DELETE FROM `channel_type` WHERE `id` = :id',
            ['id' => $id]
        );

        (new Migration1773329152AddAgenticAiChannelType())->update($this->connection);

        $row = $this->connection->fetchAssociative(
            'SELECT `icon_name` FROM `channel_type` WHERE `id` = :id',
            ['id' => $id]
        );
        static::assertIsArray($row);
        static::assertSame('regular-sparkle', $row['icon_name']);

        $translations = $this->connection->fetchAllKeyValue(
            'SELECT loc.code, sctt.name
             FROM channel_type_translation sctt
             INNER JOIN language lang ON lang.id = sctt.language_id
             INNER JOIN locale loc   ON loc.id  = lang.locale_id
             WHERE sctt.channel_type_id = :id',
            ['id' => $id]
        );
        static::assertNotCount(0, $translations);
        static::assertContains('Agentic Commerce', $translations);
    }
}
