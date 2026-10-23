<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1784801509AddIsExternalFrontendToChannelDomain;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Migration1784801509AddIsExternalFrontendToChannelDomain::class)]
class Migration1784801509AddIsExternalFrontendToChannelDomainTest extends TestCase
{
    use KernelTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var list<string>
     */
    private array $createdChannelIds = [];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        if ($this->createdChannelIds === []) {
            return;
        }

        // cascade also removes the domains that were created for these sales channels
        $this->connection->executeStatement(
            'DELETE FROM `channel` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($this->createdChannelIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->createdChannelIds = [];
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1784801509,
            (new Migration1784801509AddIsExternalFrontendToChannelDomain())->getCreationTimestamp()
        );
    }

    public function testMigrate(): void
    {
        $this->rollback();

        // idempotent
        $this->migrate();
        $this->migrate();

        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_domain', 'is_external_frontend'));

        $column = TableHelper::getColumnOfTable($this->connection, 'channel_domain', 'is_external_frontend');
        static::assertTrue($column->isNotNull);
        static::assertSame('0', (string) $column->defaultValue);

        static::assertTrue(TableHelper::columnExists($this->connection, 'channel_domain', 'external_frontend_language_id'));

        static::assertTrue(TableHelper::indexExists($this->connection, 'channel_domain', 'uniq.channel_domain.external_frontend'));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'channel_domain',
            'uniq.channel_domain.external_frontend',
            ['external_frontend_language_id', 'channel_id']
        ));
    }

    public function testExternalFrontendDomainsForDifferentChannelsWithSameLanguageAreAllowed(): void
    {
        $this->migrate();

        $firstChannelId = $this->createTestChannel();
        $secondChannelId = $this->createTestChannel();

        $this->insertDomain($firstChannelId, Defaults::LANGUAGE_SYSTEM, true);
        $this->insertDomain($secondChannelId, Defaults::LANGUAGE_SYSTEM, true);

        static::assertSame(1, $this->countExternalFrontendDomains($firstChannelId));
        static::assertSame(1, $this->countExternalFrontendDomains($secondChannelId));
    }

    public function testExternalFrontendDomainsForSameChannelWithDifferentLanguagesAreAllowed(): void
    {
        $this->migrate();

        $channelId = $this->createTestChannel();

        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, true);
        $this->insertDomain($channelId, $this->getAdditionalLanguageId(), true);

        static::assertSame(2, $this->countExternalFrontendDomains($channelId));
    }

    public function testMultipleNonExternalFrontendDomainsForSameChannelAndLanguageAreAllowed(): void
    {
        $this->migrate();

        $channelId = $this->createTestChannel();

        // non external frontend domains map to NULL and are ignored by the unique index
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, false);
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, false);

        static::assertSame(0, $this->countExternalFrontendDomains($channelId));
    }

    public function testSecondExternalFrontendDomainForSameChannelAndLanguageIsRejected(): void
    {
        $this->migrate();

        $channelId = $this->createTestChannel();

        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, true);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, true);
    }

    private function createTestChannel(): string
    {
        $channel = $this->createChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://sales-channel-' . Uuid::randomHex() . '.example',
                ],
            ],
        ]);

        $this->createdChannelIds[] = $channel['id'];

        return $channel['id'];
    }

    private function insertDomain(string $channelId, string $languageId, bool $externalFrontend): void
    {
        $this->connection->insert('channel_domain', [
            'id' => Uuid::randomBytes(),
            'channel_id' => Uuid::fromHexToBytes($channelId),
            'language_id' => Uuid::fromHexToBytes($languageId),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'snippet_set_id' => Uuid::fromHexToBytes((string) $this->getSnippetSetIdForLocale('en-GB')),
            'url' => 'https://' . Uuid::randomHex() . '.example',
            'is_external_frontend' => $externalFrontend ? 1 : 0,
            'created_at' => '2024-01-01 00:00:00.000',
        ]);
    }

    private function countExternalFrontendDomains(string $channelId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `channel_domain` WHERE `channel_id` = :id AND `is_external_frontend` = 1',
            ['id' => Uuid::fromHexToBytes($channelId)]
        );
    }

    private function getAdditionalLanguageId(): string
    {
        $languageId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `language` WHERE `id` != :systemLanguageId LIMIT 1',
            ['systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        static::assertIsString($languageId);

        return $languageId;
    }

    private function migrate(): void
    {
        (new Migration1784801509AddIsExternalFrontendToChannelDomain())->update($this->connection);
    }

    private function rollback(): void
    {
        // drop in dependency order and guard each drop independently so a half-migrated state is handled:
        // the unique index first, then the generated column that depends on `is_external_frontend`, then the flag
        if (TableHelper::indexExists($this->connection, 'channel_domain', 'uniq.channel_domain.external_frontend')) {
            $this->connection->executeStatement('ALTER TABLE `channel_domain` DROP INDEX `uniq.channel_domain.external_frontend`');
        }

        if (TableHelper::columnExists($this->connection, 'channel_domain', 'external_frontend_language_id')) {
            $this->connection->executeStatement('ALTER TABLE `channel_domain` DROP COLUMN `external_frontend_language_id`');
        }

        if (TableHelper::columnExists($this->connection, 'channel_domain', 'is_external_frontend')) {
            $this->connection->executeStatement('ALTER TABLE `channel_domain` DROP COLUMN `is_external_frontend`');
        }
    }
}
