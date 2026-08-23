<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1785930000CreateChannel;

/**
 * @internal
 */
#[CoversClass(Migration1785930000CreateChannel::class)]
class Migration1785930000CreateChannelTest extends TestCase
{
    private const array TABLES = [
        'seo_url_template',
        'seo_url',
        'cookie_consent_config_version',
        'cookie_consent_log',
        'consent_log',
        'consent_state',
        'channel_api_context',
        'member_tag',
        'member_recovery',
        'member_address',
        'member',
        'member_group_registration_channel',
        'channel_domain',
        'channel_country',
        'channel_language',
        'channel_translation',
        'channel',
        'channel_type_translation',
        'channel_type',
        'member_group_translation',
        'member_group',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testCreatesChannelBaselineIdempotently(): void
    {
        $migration = new Migration1785930000CreateChannel();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::TABLES as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
        }

        $consentStateColumns = array_column(TableHelper::getTable($this->connection, 'consent_state')->columns, 'name');
        static::assertEqualsCanonicalizing(
            ['id', 'name', 'identifier', 'state', 'actor', 'updated_at', 'revision'],
            $consentStateColumns,
        );

        $logColumns = array_column(TableHelper::getTable($this->connection, 'cookie_consent_log')->columns, 'name');
        static::assertEqualsCanonicalizing(
            ['id', 'tenant_id', 'channel_id', 'language_id', 'consent_action', 'accepted_groups', 'config_hash', 'created_at', 'updated_at'],
            $logColumns,
        );

        $configVersionColumns = array_column(TableHelper::getTable($this->connection, 'cookie_consent_config_version')->columns, 'name');
        static::assertEqualsCanonicalizing(
            ['id', 'tenant_id', 'config_hash', 'channel_id', 'language_id', 'cookie_groups', 'created_at', 'updated_at'],
            $configVersionColumns,
        );

        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'consent_state', 'updated_at')->isNotNull);
        static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'cookie_consent_log', 'updated_at')->isNotNull);
        static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'cookie_consent_config_version', 'updated_at')->isNotNull);

        foreach (['configuration', 'mail_header_footer_id', 'maintenance_ip_allowlist', 'hreflang_default_domain_id', 'business_time_zone'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'channel', $column), $column);
        }

        foreach (['home_enabled', 'home_name', 'home_meta_title', 'home_meta_description', 'home_keywords'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'channel_translation', $column), $column);
        }

        foreach (['url', 'language_id', 'snippet_set_id', 'hreflang_use_only_locale', 'is_external_frontend', 'external_frontend_language_id', 'custom_fields'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'channel_domain', $column), $column);
        }

        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'channel_domain', 'is_external_frontend')->isNotNull);
        static::assertSame('0', (string) TableHelper::getColumnOfTable($this->connection, 'channel_domain', 'is_external_frontend')->defaultValue);
        static::assertTrue(TableHelper::columnExists($this->connection, 'seo_url_template', 'is_headless'));

        foreach (['member_group_id', 'channel_id', 'language_id', 'member_number', 'name', 'phone_number', 'password', 'email', 'tag_ids'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'member', $column), $column);
        }

        static::assertFalse(TableHelper::columnExists($this->connection, 'member', 'account_type'));

        foreach (['first_name', 'last_name', 'company'] as $column) {
            static::assertFalse(TableHelper::columnExists($this->connection, 'member', $column), $column);
        }

        static::assertTrue(TableHelper::columnExists($this->connection, 'member_address', 'region_id'));
        foreach (['company', 'department'] as $column) {
            static::assertFalse(TableHelper::columnExists($this->connection, 'member_address', $column), $column);
        }

        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'channel_id'));

        static::assertTrue(TableHelper::indexExists($this->connection, 'channel', 'uniq.channel.access_key'));
        static::assertSame('UNIQUE', TableHelper::getIndexOfTable($this->connection, 'channel', 'uniq.channel.access_key')->type);
        static::assertTrue(TableHelper::indexExists($this->connection, 'channel_domain', 'uniq.channel_domain.url'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'channel_domain', 'uniq.channel_domain.external_frontend'));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'channel_domain',
            'uniq.channel_domain.external_frontend',
            ['external_frontend_language_id', 'channel_id']
        ));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'channel', 'fk.channel.hreflang_default_domain_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'channel_domain', 'fk.channel_domain.language_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'member', 'fk.member.channel_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'member_recovery', 'fk.member_recovery.member_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'member_tag', 'fk.member_tag.member_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'member_address', 'fk.member_address.region_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'channel_api_context', 'fk_channel_api_context_channel_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'channel_api_context', 'fk_channel_api_context_member_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'system_config', 'tenant_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'system_config', 'uniq.system_config.configuration_key__channel_id__tenant_id'));
        static::assertFalse(TableHelper::indexExists($this->connection, 'system_config', 'uniq.system_config.configuration_key__channel_id'));
        static::assertFalse(TableHelper::indexExists($this->connection, 'system_config', 'uniq.system_config.configuration_key'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'system_config', 'fk.system_config.tenant_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'system_config', 'fk.system_config.channel_id'));
        $updatedAt = TableHelper::getColumnOfTable($this->connection, 'channel_api_context', 'updated_at');
        static::assertTrue($updatedAt->isNotNull);
        static::assertSame('CURRENT_TIMESTAMP', strtoupper((string) $updatedAt->defaultValue));
    }

    public function testExternalFrontendDomainsForDifferentChannelsWithSameLanguageAreAllowed(): void
    {
        new Migration1785930000CreateChannel()->update($this->connection);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $languageId = Defaults::LANGUAGE_SYSTEM;
        $firstChannelId = Uuid::randomHex();
        $secondChannelId = Uuid::randomHex();

        $this->insertDomain($firstChannelId, $languageId, true);
        $this->insertDomain($secondChannelId, $languageId, true);

        static::assertSame(1, $this->countExternalFrontendDomains($firstChannelId));
        static::assertSame(1, $this->countExternalFrontendDomains($secondChannelId));
    }

    public function testExternalFrontendDomainsForSameChannelWithDifferentLanguagesAreAllowed(): void
    {
        new Migration1785930000CreateChannel()->update($this->connection);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $channelId = Uuid::randomHex();
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, true);
        $this->insertDomain($channelId, Uuid::randomHex(), true);

        static::assertSame(2, $this->countExternalFrontendDomains($channelId));
    }

    public function testMultipleNonExternalFrontendDomainsForSameChannelAndLanguageAreAllowed(): void
    {
        new Migration1785930000CreateChannel()->update($this->connection);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $channelId = Uuid::randomHex();
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, false);
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, false);

        static::assertSame(0, $this->countExternalFrontendDomains($channelId));
    }

    public function testSecondExternalFrontendDomainForSameChannelAndLanguageIsRejected(): void
    {
        new Migration1785930000CreateChannel()->update($this->connection);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $channelId = Uuid::randomHex();
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, true);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertDomain($channelId, Defaults::LANGUAGE_SYSTEM, true);
    }

    private function insertDomain(string $channelId, string $languageId, bool $externalFrontend): void
    {
        $this->connection->insert('channel_domain', [
            'id' => Uuid::randomBytes(),
            'channel_id' => Uuid::fromHexToBytes($channelId),
            'language_id' => Uuid::fromHexToBytes($languageId),
            'snippet_set_id' => Uuid::randomBytes(),
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
}
