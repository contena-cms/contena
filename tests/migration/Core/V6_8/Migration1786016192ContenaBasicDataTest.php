<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1786016192ContenaBasicData;

/**
 * @internal
 */
#[CoversClass(Migration1786016192ContenaBasicData::class)]
class Migration1786016192ContenaBasicDataTest extends TestCase
{
    private const array TABLES = [
        'acl_role',
        'media_thumbnail_size',
        'language',
        'locale',
        'locale_translation',
        'country',
        'country_translation',
        'region',
        'region_translation',
        'media_default_folder',
        'media_folder_configuration',
        'media_folder',
        'number_range_type',
        'number_range_type_translation',
        'number_range',
        'number_range_translation',
        'system_config',
        'state_machine',
        'state_machine_state',
        'state_machine_state_translation',
        'state_machine_transition',
        'state_machine_translation',
        'data_dictionary',
        'data_dictionary_translation',
        'data_dictionary_item',
        'data_dictionary_item_translation',
        'mail_template_type',
        'mail_template_type_translation',
        'mail_template',
        'mail_template_translation',
        'mail_header_footer',
        'mail_header_footer_translation',
        'rule',
        'rule_condition',
        'flow',
        'flow_sequence',
        'flow_template',
        'seo_url_template',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->createTemporaryTables();
    }

    protected function tearDown(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement(\sprintf('DROP TEMPORARY TABLE IF EXISTS `%s`', $table));
        }
    }

    public function testSeedsBasicDataIdempotently(): void
    {
        $migration = $this->createMigration();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $defaultRole = $this->connection->fetchAssociative(
            'SELECT `code`, `name`, `description`, `privileges` FROM `acl_role` WHERE `code` = :code',
            ['code' => 'administrator']
        );
        static::assertIsArray($defaultRole);
        static::assertSame('administrator', $defaultRole['code']);
        static::assertSame('管理员', $defaultRole['name']);
        static::assertSame('拥有系统管理所需权限。', $defaultRole['description']);

        $defaultPrivileges = json_decode((string) $defaultRole['privileges'], true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($defaultPrivileges);
        static::assertContains('users_and_permissions.deleter', $defaultPrivileges);
        static::assertContains('system.system_config', $defaultPrivileges);
        static::assertContains('flow:delete', $defaultPrivileges);
        static::assertContains('blog:delete', $defaultPrivileges);
        static::assertContains('category:delete', $defaultPrivileges);
        static::assertContains('channel:delete', $defaultPrivileges);
        static::assertContains('content_layout:delete', $defaultPrivileges);
        static::assertContains('footer_content_layout:delete', $defaultPrivileges);
        static::assertContains('header_content_layout:delete', $defaultPrivileges);
        static::assertContains('landing_page:delete', $defaultPrivileges);
        static::assertContains('member:delete', $defaultPrivileges);
        static::assertContains('snippet:delete', $defaultPrivileges);
        static::assertContains('theme:delete', $defaultPrivileges);
        static::assertContains('experience_studio.deleter', $defaultPrivileges);
        static::assertContains('member_groups.deleter', $defaultPrivileges);
        static::assertContains('theme.deleter', $defaultPrivileges);
        static::assertNotContains('all', $defaultPrivileges);
        static::assertNotContains('system.plugin_maintain', $defaultPrivileges);
        static::assertNotContains('system.extension_store', $defaultPrivileges);
        static::assertNotContains('plugin:read', $defaultPrivileges);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `acl_role`'));

        static::assertSame(
            ['en-GB', 'zh-CN'],
            $this->connection->fetchFirstColumn('SELECT `code` FROM `locale` ORDER BY `code`')
        );
        static::assertSame(
            ['English', '简体中文'],
            $this->connection->fetchFirstColumn('SELECT `name` FROM `language` ORDER BY `name`')
        );
        static::assertSame(
            Defaults::DEFAULT_LOCALE,
            $this->connection->fetchOne(
                'SELECT `locale`.`code`
                 FROM `language`
                 INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
                 WHERE `language`.`id` = :languageId',
                ['languageId' => hex2bin(Defaults::LANGUAGE_SYSTEM)]
            )
        );
        static::assertSame(
            ['CN', 'DE', 'GB', 'JP', 'US'],
            $this->connection->fetchFirstColumn('SELECT `iso` FROM `country` ORDER BY `iso`')
        );
        static::assertSame(10, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `country_translation`'));
        static::assertSame(34, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `region` WHERE `parent_id` IS NULL'));
        static::assertSame(3840, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `region`'));
        static::assertSame(7680, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `region_translation`'));
        static::assertSame(
            ['member', 'user'],
            $this->connection->fetchFirstColumn('SELECT `technical_name` FROM `number_range_type` ORDER BY `technical_name`')
        );
        static::assertSame(
            ['{n}', '{n}'],
            $this->connection->fetchFirstColumn('SELECT `pattern` FROM `number_range` ORDER BY `pattern`')
        );
        static::assertSame(
            [10, 10],
            array_map(
                static fn (string $start): int => (int) $start,
                $this->connection->fetchFirstColumn('SELECT `start` FROM `number_range` ORDER BY `start`')
            )
        );
        static::assertSame(
            'core.userPermission.passwordMinLength',
            $this->connection->fetchOne(
                'SELECT `configuration_key` FROM `system_config` WHERE `configuration_key` LIKE :passwordMinLength',
                ['passwordMinLength' => '%.passwordMinLength']
            )
        );
        static::assertSame(
            8,
            (int) $this->connection->fetchOne(
                'SELECT JSON_UNQUOTE(JSON_EXTRACT(`configuration_value`, \'$._value\'))
                 FROM `system_config`
                 WHERE `configuration_key` = :configurationKey',
                ['configurationKey' => 'core.userPermission.passwordMinLength']
            )
        );
        static::assertSame(
            [
                'core.basicInformation.siteName' => 'Contena',
                'core.basicInformation.useDefaultCookieConsent' => 'true',
            ],
            $this->connection->fetchAllKeyValue(
                'SELECT `configuration_key`, JSON_UNQUOTE(JSON_EXTRACT(`configuration_value`, \'$._value\'))
                 FROM `system_config`
                 WHERE `configuration_key` LIKE :basicInformation
                   AND `configuration_key` <> :activeCaptchas
                 ORDER BY `configuration_key`',
                [
                    'basicInformation' => 'core.basicInformation.%',
                    'activeCaptchas' => 'core.basicInformation.activeCaptchasV2',
                ]
            )
        );
        static::assertEquals(
            [
                'honeypot' => ['name' => 'Honeypot', 'isActive' => false],
                'basicCaptcha' => ['name' => 'basicCaptcha', 'isActive' => false],
                'googleReCaptchaV2' => [
                    'name' => 'googleReCaptchaV2',
                    'config' => ['siteKey' => '', 'invisible' => false, 'secretKey' => ''],
                    'isActive' => false,
                ],
                'googleReCaptchaV3' => [
                    'name' => 'googleReCaptchaV3',
                    'config' => ['siteKey' => '', 'secretKey' => '', 'thresholdScore' => 0.5],
                    'isActive' => false,
                ],
            ],
            json_decode(
                (string) $this->connection->fetchOne(
                    'SELECT JSON_UNQUOTE(JSON_EXTRACT(`configuration_value`, \'$._value\'))
                     FROM `system_config`
                     WHERE `configuration_key` = :configurationKey',
                    ['configurationKey' => 'core.basicInformation.activeCaptchasV2']
                ),
                true,
                512,
                \JSON_THROW_ON_ERROR
            )
        );
        static::assertSame(
            [
                'core.sitemap.excludeLinkedBlogs' => 'false',
                'core.sitemap.sitemapRefreshStrategy' => '2',
                'core.sitemap.sitemapRefreshTime' => '3600',
            ],
            $this->connection->fetchAllKeyValue(
                'SELECT `configuration_key`, JSON_UNQUOTE(JSON_EXTRACT(`configuration_value`, \'$._value\'))
                 FROM `system_config`
                 WHERE `configuration_key` LIKE :sitemapConfiguration
                 ORDER BY `configuration_key`',
                ['sitemapConfiguration' => 'core.sitemap.%']
            )
        );
        static::assertFalse($this->connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :configurationKey',
            ['configurationKey' => 'core.loginRegistration.passwordMinLength']
        ));
        static::assertSame(
            ['female', 'male', 'undisclosed'],
            $this->connection->fetchFirstColumn(
                'SELECT `data_dictionary_item`.`code`
                 FROM `data_dictionary_item`
                 INNER JOIN `data_dictionary`
                    ON `data_dictionary`.`id` = `data_dictionary_item`.`dictionary_id`
                 WHERE `data_dictionary`.`technical_name` = :technicalName
                 ORDER BY `data_dictionary_item`.`code`',
                ['technicalName' => 'core.gender']
            )
        );
        static::assertSame(
            ['女', '男', '保密'],
            $this->connection->fetchFirstColumn(
                'SELECT `data_dictionary_item_translation`.`label`
                 FROM `data_dictionary_item_translation`
                 INNER JOIN `data_dictionary_item`
                    ON `data_dictionary_item`.`id` = `data_dictionary_item_translation`.`data_dictionary_item_id`
                 INNER JOIN `data_dictionary`
                    ON `data_dictionary`.`id` = `data_dictionary_item`.`dictionary_id`
                 INNER JOIN `language`
                    ON `language`.`id` = `data_dictionary_item_translation`.`language_id`
                 INNER JOIN `locale`
                    ON `locale`.`id` = `language`.`translation_code_id`
                 WHERE `data_dictionary`.`technical_name` = :technicalName
                   AND `locale`.`code` = :locale
                 ORDER BY `data_dictionary_item`.`code`',
                ['technicalName' => 'core.gender', 'locale' => Defaults::DEFAULT_LOCALE]
            )
        );
        static::assertSame(
            ['city', 'district', 'province'],
            $this->connection->fetchFirstColumn(
                'SELECT `data_dictionary_item`.`code`
                 FROM `data_dictionary_item`
                 INNER JOIN `data_dictionary`
                    ON `data_dictionary`.`id` = `data_dictionary_item`.`dictionary_id`
                 WHERE `data_dictionary`.`technical_name` = :technicalName
                 ORDER BY `data_dictionary_item`.`code`',
                ['technicalName' => 'core.region.type']
            )
        );
        static::assertSame(
            ['市级', '区县级', '省级'],
            $this->connection->fetchFirstColumn(
                'SELECT `data_dictionary_item_translation`.`label`
                 FROM `data_dictionary_item_translation`
                 INNER JOIN `data_dictionary_item`
                    ON `data_dictionary_item`.`id` = `data_dictionary_item_translation`.`data_dictionary_item_id`
                 INNER JOIN `data_dictionary`
                    ON `data_dictionary`.`id` = `data_dictionary_item`.`dictionary_id`
                 INNER JOIN `language`
                    ON `language`.`id` = `data_dictionary_item_translation`.`language_id`
                 INNER JOIN `locale`
                    ON `locale`.`id` = `language`.`translation_code_id`
                 WHERE `data_dictionary`.`technical_name` = :technicalName
                   AND `locale`.`code` = :locale
                 ORDER BY `data_dictionary_item`.`code`',
                ['technicalName' => 'core.region.type', 'locale' => Defaults::DEFAULT_LOCALE]
            )
        );
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => 'user.recovery.request']
        ));
        static::assertSame(
            ['userRecovery' => 'user_recovery'],
            json_decode((string) $this->connection->fetchOne(
                'SELECT `available_entities` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
                ['technicalName' => 'user.recovery.request']
            ), true, flags: \JSON_THROW_ON_ERROR)
        );
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template`
             INNER JOIN `mail_template_type` ON `mail_template_type`.`id` = `mail_template`.`mail_template_type_id`
             WHERE `mail_template_type`.`technical_name` = :technicalName AND `mail_template`.`system_default` = 1',
            ['technicalName' => 'user.recovery.request']
        ));
        static::assertSame(
            ['Reset your Contena password', '重置您的 Contena 密码'],
            $this->connection->fetchFirstColumn(
                'SELECT `mail_template_translation`.`subject`
                 FROM `mail_template_translation`
                 INNER JOIN `mail_template` ON `mail_template`.`id` = `mail_template_translation`.`mail_template_id`
                 INNER JOIN `mail_template_type` ON `mail_template_type`.`id` = `mail_template`.`mail_template_type_id`
                 WHERE `mail_template_type`.`technical_name` = :technicalName
                 ORDER BY `mail_template_translation`.`subject`',
                ['technicalName' => 'user.recovery.request']
            )
        );
        static::assertSame(2, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template_translation`
             WHERE `content_html` LIKE :userRecovery AND `content_plain` LIKE :resetUrl',
            ['userRecovery' => '%userRecovery.user%', 'resetUrl' => '%resetUrl%']
        ));
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_header_footer` WHERE `system_default` = 1'
        ));
        static::assertSame(
            ['Contena default header and footer', 'Contena 默认页眉和页脚'],
            $this->connection->fetchFirstColumn(
                'SELECT `name` FROM `mail_header_footer_translation` ORDER BY `name`'
            )
        );
        static::assertSame(
            ['Default header and footer for system emails.', '用于系统邮件的默认页眉和页脚。'],
            $this->connection->fetchFirstColumn(
                'SELECT `description` FROM `mail_header_footer_translation` ORDER BY `description`'
            )
        );
        static::assertSame(2, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_header_footer_translation`
             WHERE `header_html` LIKE :brand AND `footer_html` LIKE :automated',
            ['brand' => '%Contena%', 'automated' => '%Contena%']
        ));
        static::assertSame(
            ['mail_template', 'user'],
            $this->connection->fetchFirstColumn('SELECT `entity` FROM `media_default_folder` ORDER BY `entity`')
        );
        static::assertSame(
            ['Mail Template Media', 'User Media'],
            $this->connection->fetchFirstColumn('SELECT `name` FROM `media_folder` ORDER BY `name`')
        );
        static::assertSame(
            ['工作日', '工作时间', '系统语言'],
            $this->connection->fetchFirstColumn('SELECT `name` FROM `rule` ORDER BY `name`')
        );
        static::assertSame(8, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `rule_condition`'));
        static::assertSame(
            ['dayOfWeek', 'language', 'orContainer', 'timeRange'],
            $this->connection->fetchFirstColumn('SELECT DISTINCT `type` FROM `rule_condition` ORDER BY `type`')
        );
        static::assertSame(
            'action.mail.send',
            $this->connection->fetchOne(
                'SELECT `flow_sequence`.`action_name`
                 FROM `flow_sequence`
                 INNER JOIN `flow` ON `flow`.`id` = `flow_sequence`.`flow_id`
                 WHERE `flow`.`event_name` = :eventName AND `flow`.`active` = 1',
                ['eventName' => 'user.recovery.request']
            )
        );
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `flow_template`
             WHERE JSON_UNQUOTE(JSON_EXTRACT(`config`, \'$.eventName\')) = :eventName',
            ['eventName' => 'user.recovery.request']
        ));
    }

    public function testSkipsTheBaselineWhenLanguageDataAlreadyExists(): void
    {
        $createdAt = new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $zhLocaleId = Uuid::randomBytes();

        $this->connection->insert('locale', [
            'id' => $zhLocaleId,
            'code' => Defaults::DEFAULT_LOCALE,
            'created_at' => $createdAt,
        ]);
        $this->connection->insert('language', [
            'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => '简体中文',
            'locale_id' => $zhLocaleId,
            'translation_code_id' => $zhLocaleId,
            'active' => 1,
            'created_at' => $createdAt,
        ]);

        $migration = $this->createMigration();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            ['简体中文'],
            $this->connection->fetchFirstColumn('SELECT `name` FROM `language` ORDER BY `name`')
        );
        static::assertSame(
            ['zh-CN'],
            $this->connection->fetchFirstColumn('SELECT `code` FROM `locale` ORDER BY `code`')
        );
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `acl_role`'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `state_machine`'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `mail_template_type`'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `media_default_folder`'));
    }

    public function testSeedsChannelApiSeoUrlTemplates(): void
    {
        $migration = new Migration1786016192ContenaBasicData();
        $method = new \ReflectionMethod($migration, 'createDefaultSeoUrlTemplates');
        $method->invoke($migration, $this->connection, '2024-01-01 00:00:00.000');

        $channelApiDefaults = $this->connection->fetchAllKeyValue(
            'SELECT route_name, template FROM seo_url_template
             WHERE channel_id IS NULL AND is_headless = 1 AND route_name LIKE :prefix',
            ['prefix' => 'channel-api.%']
        );

        static::assertArrayHasKey('channel-api.blog.detail', $channelApiDefaults);
        static::assertArrayHasKey('channel-api.category.detail', $channelApiDefaults);
        static::assertArrayHasKey('channel-api.landing-page.detail', $channelApiDefaults);

        $frontendBlog = $this->connection->fetchOne(
            'SELECT template FROM seo_url_template
             WHERE entity_name = :entity AND channel_id IS NULL AND is_headless = 0',
            ['entity' => 'blog']
        );
        static::assertSame($frontendBlog, $channelApiDefaults['channel-api.blog.detail']);
    }

    private function createMigration(): Migration1786016192ContenaBasicData
    {
        return new class extends Migration1786016192ContenaBasicData {
            protected function createDomainDefaultData(Connection $connection): void
            {
            }
        };
    }

    private function createTemporaryTables(): void
    {
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `seo_url_template` (
                `id` BINARY(16) NOT NULL,
                `channel_id` BINARY(16) NULL,
                `route_name` VARCHAR(255) NOT NULL,
                `entity_name` VARCHAR(64) NOT NULL,
                `template` VARCHAR(750) NULL,
                `is_valid` TINYINT(1) NOT NULL DEFAULT 1,
                `is_headless` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.seo_url_template.route_name` (`channel_id`, `route_name`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `acl_role` (
                `id` BINARY(16) NOT NULL,
                `code` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` LONGTEXT NULL,
                `privileges` JSON NOT NULL,
                `deleted_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.acl_role.code` (`code`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `media_thumbnail_size` (
                `id` BINARY(16) NOT NULL,
                `width` INT NOT NULL,
                `height` INT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `locale` (
                `id` BINARY(16) NOT NULL,
                `code` VARCHAR(35) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `language` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(50) NOT NULL,
                `locale_id` BINARY(16) NOT NULL,
                `translation_code_id` BINARY(16) NOT NULL,
                `active` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `locale_translation` (
                `locale_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `territory` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `country` (
                `id` BINARY(16) NOT NULL,
                `iso` VARCHAR(2) NOT NULL,
                `iso3` VARCHAR(3) NOT NULL,
                `position` INT NOT NULL,
                `active` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `country_translation` (
                `country_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `region` (
                `id` BINARY(16) NOT NULL,
                `country_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `level` INT NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `code` VARCHAR(64) NULL,
                `path` LONGTEXT NULL,
                `child_count` INT NOT NULL,
                `position` INT NOT NULL,
                `active` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `region_translation` (
                `region_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `short_name` VARCHAR(100) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`region_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `media_default_folder` (
                `id` BINARY(16) NOT NULL,
                `entity` VARCHAR(64) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `media_folder_configuration` (
                `id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `media_folder` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `default_folder_id` BINARY(16) NOT NULL,
                `media_folder_configuration_id` BINARY(16) NOT NULL,
                `use_parent_configuration` TINYINT(1) NOT NULL,
                `child_count` INT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `system_config` (
                `id` BINARY(16) NOT NULL,
                `tenant_id` BINARY(16) NULL,
                `configuration_key` VARCHAR(255) NOT NULL,
                `configuration_value` JSON NOT NULL,
                `channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `state_machine` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `initial_state_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE (`technical_name`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `state_machine_state` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `state_machine_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE (`state_machine_id`, `technical_name`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `state_machine_state_translation` (
                `state_machine_state_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`state_machine_state_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `state_machine_transition` (
                `id` BINARY(16) NOT NULL,
                `action_name` VARCHAR(255) NOT NULL,
                `state_machine_id` BINARY(16) NOT NULL,
                `from_state_id` BINARY(16) NOT NULL,
                `to_state_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE (`state_machine_id`, `action_name`, `from_state_id`, `to_state_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `state_machine_translation` (
                `state_machine_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`state_machine_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `number_range_type` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NULL,
                `global` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE (`technical_name`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `number_range_type_translation` (
                `number_range_type_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `type_name` VARCHAR(64) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`number_range_type_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `number_range` (
                `id` BINARY(16) NOT NULL,
                `type_id` BINARY(16) NOT NULL,
                `global` TINYINT(1) NOT NULL,
                `pattern` VARCHAR(255) NOT NULL,
                `start` INTEGER NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `number_range_translation` (
                `number_range_id` BINARY(16) NOT NULL,
                `name` VARCHAR(64) NULL,
                `description` VARCHAR(255) NULL,
                `custom_fields` JSON NULL,
                `language_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`number_range_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `data_dictionary` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `active` TINYINT(1) NOT NULL,
                `system_locked` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE (`technical_name`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `data_dictionary_translation` (
                `data_dictionary_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `description` TEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`data_dictionary_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `data_dictionary_item` (
                `id` BINARY(16) NOT NULL,
                `dictionary_id` BINARY(16) NOT NULL,
                `code` VARCHAR(255) NOT NULL,
                `position` INT NOT NULL,
                `active` TINYINT(1) NOT NULL,
                `system_locked` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE (`dictionary_id`, `code`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `data_dictionary_item_translation` (
                `data_dictionary_item_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`data_dictionary_item_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `mail_template_type` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `available_entities` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE (`technical_name`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `mail_template_type_translation` (
                `mail_template_type_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`mail_template_type_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `mail_template` (
                `id` BINARY(16) NOT NULL,
                `mail_template_type_id` BINARY(16) NULL,
                `system_default` TINYINT(1) NOT NULL,
                `was_modified_by_user` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `mail_template_translation` (
                `mail_template_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `sender_name` VARCHAR(255) NULL,
                `subject` VARCHAR(255) NULL,
                `description` LONGTEXT NULL,
                `content_html` LONGTEXT NULL,
                `content_plain` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`mail_template_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `mail_header_footer` (
                `id` BINARY(16) NOT NULL,
                `system_default` TINYINT(1) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `mail_header_footer_translation` (
                `mail_header_footer_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `description` LONGTEXT NULL,
                `header_html` LONGTEXT NULL,
                `header_plain` LONGTEXT NULL,
                `footer_html` LONGTEXT NULL,
                `footer_plain` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`mail_header_footer_id`, `language_id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `rule` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(500) NOT NULL,
                `description` LONGTEXT NULL,
                `priority` INT NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `rule_condition` (
                `id` BINARY(16) NOT NULL,
                `type` VARCHAR(255) NOT NULL,
                `rule_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `value` JSON NULL,
                `position` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `flow` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` VARCHAR(500) NULL,
                `event_name` VARCHAR(255) NOT NULL,
                `priority` INT NOT NULL DEFAULT 1,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `flow_sequence` (
                `id` BINARY(16) NOT NULL,
                `flow_id` BINARY(16) NOT NULL,
                `action_name` VARCHAR(255) NULL,
                `config` JSON NULL,
                `position` INT NOT NULL DEFAULT 1,
                `display_group` INT NOT NULL DEFAULT 1,
                `true_case` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->executeStatement(
            'CREATE TEMPORARY TABLE `flow_template` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `config` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`)
            )'
        );
    }
}
