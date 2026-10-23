<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1773826242RenameAgenticCommerceChannelType;

/**
 * @internal
 */
#[CoversClass(Migration1773826242RenameAgenticCommerceChannelType::class)]
class Migration1773826242RenameAgenticCommerceChannelTypeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773826242, (new Migration1773826242RenameAgenticCommerceChannelType())->getCreationTimestamp());
    }

    public function testMigrationRenamesAgenticCommerceTranslations(): void
    {
        $channelTypeId = Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE);

        $existingTranslations = $this->connection->fetchAllAssociative(
            'SELECT sctt.language_id, loc.code
             FROM channel_type_translation sctt
             INNER JOIN language lang ON lang.id = sctt.language_id
             INNER JOIN locale loc ON loc.id = lang.locale_id
             WHERE sctt.channel_type_id = :channelTypeId',
            ['channelTypeId' => $channelTypeId]
        );

        static::assertNotCount(0, $existingTranslations);

        $this->connection->update(
            'channel_type_translation',
            [
                'name' => 'Old Name',
                'manufacturer' => 'Old Manufacturer',
                'description' => 'Old Description',
            ],
            ['channel_type_id' => $channelTypeId]
        );

        $migration = new Migration1773826242RenameAgenticCommerceChannelType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT sctt.name, sctt.manufacturer, sctt.description, loc.code
             FROM channel_type_translation sctt
             INNER JOIN language lang ON lang.id = sctt.language_id
             INNER JOIN locale loc ON loc.id = lang.locale_id
             WHERE sctt.channel_type_id = :channelTypeId',
            ['channelTypeId' => $channelTypeId]
        );

        static::assertCount(\count($existingTranslations), $translations);

        foreach ($translations as $translation) {
            static::assertSame('Agentic Commerce', $translation['name']);
            static::assertSame('Contena', $translation['manufacturer']);

            if ($translation['code'] === 'de-DE') {
                static::assertSame('Verkaufskanal für Agentic-Commerce-Plattformen', $translation['description']);

                continue;
            }

            static::assertSame('Sales channel for agentic commerce platforms', $translation['description']);
        }
    }
}
