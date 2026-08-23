<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\Traits\ImportTranslationsTrait;
use Contena\Core\Migration\Traits\StateMachineMigrationTrait;
use Contena\Core\Migration\Traits\Translations;
use Contena\Core\System\DataDictionary\DataDictionaryDefinition;

/**
 * @internal
 */
class Migration1786016192ContenaBasicData extends MigrationStep
{
    use ImportTranslationsTrait;
    use StateMachineMigrationTrait;

    private const string DEFAULT_ADMINISTRATOR_ROLE_ID = '019fcbf8e98e7c93bfa2d14cb01db101';

    /**
     * Entity privileges available in the core installation at this migration.
     * Plugin entities are intentionally absent.
     */
    private const array DEFAULT_ADMINISTRATOR_RESOURCES = [
        'acl_role',
        'acl_user_role',
        'blog',
        'blog_category',
        'blog_category_tree',
        'blog_content_layout',
        'blog_keyword_dictionary',
        'blog_main_category',
        'blog_media',
        'blog_search_config',
        'blog_search_config_field',
        'blog_search_keyword',
        'blog_sorting',
        'blog_sorting_translation',
        'blog_tag',
        'blog_translation',
        'blog_visibility',
        'category',
        'category_content_layout',
        'category_tag',
        'category_translation',
        'channel',
        'channel_analytics',
        'channel_country',
        'channel_domain',
        'channel_file',
        'channel_language',
        'channel_translation',
        'channel_type',
        'channel_type_translation',
        'content_layout',
        'cookie_consent_config_version',
        'cookie_consent_log',
        'country',
        'country_translation',
        'custom_field',
        'custom_field_set',
        'custom_field_set_relation',
        'data_dictionary',
        'data_dictionary_item',
        'data_dictionary_item_translation',
        'data_dictionary_translation',
        'flow',
        'flow_sequence',
        'flow_template',
        'footer_content_layout',
        'header_content_layout',
        'integration',
        'integration_role',
        'language',
        'landing_page',
        'landing_page_channel',
        'landing_page_content_layout',
        'landing_page_tag',
        'landing_page_translation',
        'locale',
        'locale_translation',
        'log_entry',
        'mail_header_footer',
        'mail_header_footer_translation',
        'mail_template',
        'mail_template_media',
        'mail_template_translation',
        'mail_template_type',
        'mail_template_type_translation',
        'media',
        'media_default_folder',
        'media_folder',
        'media_folder_configuration',
        'media_folder_configuration_media_thumbnail_size',
        'media_tag',
        'media_thumbnail',
        'media_thumbnail_size',
        'media_translation',
        'member',
        'member_address',
        'member_group',
        'member_group_registration_channel',
        'member_group_translation',
        'member_recovery',
        'member_tag',
        'notification',
        'number_range',
        'number_range_state',
        'number_range_translation',
        'number_range_type',
        'number_range_type_translation',
        'organization',
        'organization_translation',
        'organization_unit',
        'organization_unit_translation',
        'position',
        'position_translation',
        'region',
        'region_translation',
        'rule',
        'rule_condition',
        'rule_tag',
        'scheduled_task',
        'seo_url',
        'seo_url_template',
        'snippet',
        'snippet_set',
        'state_machine',
        'state_machine_history',
        'state_machine_state',
        'state_machine_state_translation',
        'state_machine_transition',
        'state_machine_translation',
        'system:translation',
        'system_config',
        'tag',
        'theme',
        'theme_channel',
        'theme_child',
        'theme_media',
        'theme_translation',
        'user',
        'user_access_key',
        'user_config',
        'user_position',
        'user_recovery',
        'user_tag',
        'version',
        'version_commit',
        'version_commit_data',
    ];

    private const array DEFAULT_ADMINISTRATOR_FUNCTIONS = [
        'blog',
        'category',
        'channel',
        'country',
        'custom_field',
        'data_dictionary',
        'experience_studio',
        'flow',
        'integration',
        'language',
        'landing_page',
        'mail_templates',
        'media',
        'member',
        'member_groups',
        'organization',
        'position',
        'region',
        'rule',
        'tag',
        'theme',
        'users_and_permissions',
    ];

    private const array DEFAULT_ADMINISTRATOR_SPECIAL_PRIVILEGES = [
        'api_acl_privileges_additional_get',
        'api_acl_privileges_get',
        'api_action_access-key_integration',
        'api_action_cache_index',
        'api_action_integration_mcp-allowlist',
        'api_action_user_mcp-allowlist',
        'api_feature_flag_toggle',
        'api_send_email',
        'increment:manage',
        'message_queue_stats:read',
        'system.clear_cache',
        'system.logging',
        'system.system_config',
        'system:cache:info',
        'system:clear:cache',
        'user.update_profile',
        'user_change_me',
    ];

    private const array DEFAULT_UNITS = [
        'company' => ['position' => 10, 'zh-CN' => '公司', 'en-GB' => 'Company'],
        'department' => ['position' => 20, 'zh-CN' => '部门', 'en-GB' => 'Department'],
    ];

    private const array DEFAULT_ORGANIZATIONS = [
        'HQ' => [
            'unit' => 'company',
            'parent' => null,
            'position' => 10,
            'zh-CN' => '总部',
            'en-GB' => 'Headquarters',
        ],
        'FIN' => [
            'unit' => 'department',
            'parent' => 'HQ',
            'position' => 10,
            'zh-CN' => '财务部',
            'en-GB' => 'Finance Department',
        ],
    ];

    private const array DEFAULT_POSITIONS = [
        'general_manager' => ['position' => 10, 'zh-CN' => '总经理', 'en-GB' => 'General Manager'],
        'department_manager' => ['position' => 20, 'zh-CN' => '部门经理', 'en-GB' => 'Department Manager'],
    ];

    private const string DEFAULT_MEMBER_GROUP_ID = 'cfbd5018d38d41d8adca10d94fc8bdd6';

    private const string DEFAULT_API_CHANNEL_ID = '98432def39fc4624b33213a56b8c944d';

    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    private const string DEFAULT_NAVIGATION_CATEGORY_ID = 'a4d2e5324fea4bc5a950142b6c7f86e3';

    private const string DEFAULT_BLOG_CATEGORY_ID = '5d458f935413f3a91cb77d8b345ccac5';

    private const string DEFAULT_LANDING_PAGE_CATEGORY_ID = '940745faa50709c11a60281cfbfb687d';

    private const string DEFAULT_BLOG_ID = '3bdbb2474ffec6bfc96342ec3f4a75a0';

    private const string DEFAULT_LANDING_PAGE_ID = '43d1adaa1e699b09cb48643eadd87efb';

    private const string DEFAULT_BLOG_LAYOUT_ID = '4c5521c0ef05a4a84f83cdbade6ae1f8';

    private const string DEFAULT_CATEGORY_LAYOUT_ID = '2cf5b821df7ea384855b3fc4c34e06e8';

    private const string DEFAULT_LANDING_PAGE_LAYOUT_ID = '761d10258bef0a6e59e58b916428a2e4';

    private const string CORE_GENDER_DICTIONARY_ID = '019f7934e3e971858ed277eafea95ae7';

    private const array CORE_GENDER_ITEMS = [
        'male' => ['id' => '019f7934e3e971858ed277eafef671d9', 'position' => 10, 'zh-CN' => '男', 'en-GB' => 'Male'],
        'female' => ['id' => '019f7934e3e971858ed277eafef879c2', 'position' => 20, 'zh-CN' => '女', 'en-GB' => 'Female'],
        'undisclosed' => ['id' => '019f7934e3e971858ed277eaff3141e4', 'position' => 30, 'zh-CN' => '保密', 'en-GB' => 'Undisclosed'],
    ];

    private const string CORE_REGION_TYPE_DICTIONARY_ID = '019f7934e3e971858ed277eb1b000001';

    private const array CORE_REGION_TYPE_ITEMS = [
        'province' => ['id' => '019f7934e3e971858ed277eb1b000002', 'position' => 10, 'zh-CN' => '省级', 'en-GB' => 'Province'],
        'city' => ['id' => '019f7934e3e971858ed277eb1b000003', 'position' => 20, 'zh-CN' => '市级', 'en-GB' => 'City'],
        'district' => ['id' => '019f7934e3e971858ed277eb1b000004', 'position' => 30, 'zh-CN' => '区县级', 'en-GB' => 'District'],
    ];

    private const string WORKING_DAYS_RULE_ID = '019fc5b5ad1f7a659e3eea39f1000101';

    private const string WORKING_HOURS_RULE_ID = '019fc5b5ad1f7a659e3eea39f1000102';

    private const string SYSTEM_LANGUAGE_RULE_ID = '019fc5b5ad1f7a659e3eea39f1000103';

    private const string USER_RECOVERY_FLOW_ID = '019fc5b5ad1f7a659e3eea39f1000201';

    private const string USER_RECOVERY_FLOW_SEQUENCE_ID = '019fc5b5ad1f7a659e3eea39f1000202';

    private const string USER_RECOVERY_FLOW_TEMPLATE_ID = '019fc5b5ad1f7a659e3eea39f1000203';

    private const array DEFAULT_MEDIA_FOLDERS = [
        'user' => 'User Media',
        'mail_template' => 'Mail Template Media',
    ];

    private const array COUNTRIES = [
        ['iso' => 'CN', 'iso3' => 'CHN', 'position' => 1, 'zh-CN' => '中国', 'en-GB' => 'China'],
        ['iso' => 'US', 'iso3' => 'USA', 'position' => 2, 'zh-CN' => '美国', 'en-GB' => 'United States'],
        ['iso' => 'GB', 'iso3' => 'GBR', 'position' => 3, 'zh-CN' => '英国', 'en-GB' => 'United Kingdom'],
        ['iso' => 'JP', 'iso3' => 'JPN', 'position' => 4, 'zh-CN' => '日本', 'en-GB' => 'Japan'],
        ['iso' => 'DE', 'iso3' => 'DEU', 'position' => 5, 'zh-CN' => '德国', 'en-GB' => 'Germany'],
    ];

    private ?string $enGbLanguageId = null;

    public function getCreationTimestamp(): int
    {
        return 1786016192;
    }

    public function update(Connection $connection): void
    {
        $hasData = $connection->executeQuery('SELECT 1 FROM `language` LIMIT 1')->fetchAssociative();
        if ($hasData) {
            return;
        }

        $this->createLanguage($connection);
        $this->createDefaultSnippetSets($connection);
        $this->createDefaultAdministratorRole($connection);
        $mailTemplate = $this->createUserRecoveryMailTemplate($connection);
        $this->createDefaultMailHeaderFooter($connection);
        $this->createDefaultMediaFolders($connection);
        $this->createDefaultRules($connection);
        $this->createUserRecoveryFlow($connection, $mailTemplate['templateId'], $mailTemplate['typeId']);
        $this->registerIndexer($connection, 'rule.indexer');
        $this->registerIndexer($connection, 'flow.indexer');
        $this->createSystemConfigOptions($connection);
        $this->ensureDefaultMediaThumbnailSize($connection);
        $this->createCountries($connection);
        $this->initializeChinaRegions($connection);
        $this->registerIndexer($connection, 'region.indexer');
        $this->registerIndexer($connection, 'media_folder.indexer');
        $this->registerIndexer($connection, 'media_folder_configuration.indexer');
        $this->createCoreGenderDictionary($connection);
        $this->createCoreRegionTypeDictionary($connection);
        $this->createUserNumberRange($connection);
        $this->createMemberNumberRange($connection);
        $this->createDomainDefaultData($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * Seeds the bundled province, city and district hierarchy for China.
     */
    public function initializeChinaRegions(Connection $connection): void
    {
        $countryId = $connection->fetchOne('SELECT `id` FROM `country` WHERE `iso` = :iso', ['iso' => 'CN']);

        $contents = file_get_contents(__DIR__ . '/Fixtures/china-regions.json');
        if (!\is_string($contents)) {
            return;
        }

        $data = Json::decodeToArray($contents);
        if (!\is_array($data['regions'] ?? null)) {
            return;
        }

        $languages = array_map(
            static fn (array $language): array => [
                'id' => (string) $language['id'],
                'code' => (string) $language['code'],
            ],
            $connection->fetchAllAssociative(
                'SELECT `language`.`id`, `locale`.`code`
             FROM `language`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`translation_code_id`
             WHERE `locale`.`code` IN (:zh, :en)',
                ['zh' => Defaults::DEFAULT_LOCALE, 'en' => 'en-GB']
            )
        );

        foreach ($data['regions'] as $position => $region) {
            if (\is_array($region)) {
                $this->insertChinaRegion($connection, $countryId, $region, null, [], $position + 1, $languages);
            }
        }
    }

    protected function createDomainDefaultData(Connection $connection): void
    {
        $this->seedOrganizationData($connection);
        $this->seedPositionData($connection);
        $this->seedBlogSortingDefaults($connection);
        $this->createBlogSearchDefaults($connection);
        $this->seedChannelData($connection);
    }

    private function createDefaultAdministratorRole(Connection $connection): void
    {
        $privileges = self::DEFAULT_ADMINISTRATOR_SPECIAL_PRIVILEGES;

        foreach (self::DEFAULT_ADMINISTRATOR_RESOURCES as $resource) {
            foreach (['read', 'create', 'update', 'delete'] as $operation) {
                $privileges[] = $resource . ':' . $operation;
            }
        }

        foreach (self::DEFAULT_ADMINISTRATOR_FUNCTIONS as $function) {
            foreach (['viewer', 'editor', 'creator', 'deleter'] as $role) {
                $privileges[] = $function . '.' . $role;
            }
        }

        sort($privileges);

        $connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes(self::DEFAULT_ADMINISTRATOR_ROLE_ID),
            'code' => 'administrator',
            'name' => '管理员',
            'description' => '拥有系统管理所需权限。',
            'privileges' => json_encode(array_values(array_unique($privileges)), \JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt(),
        ]);
    }

    /**
     * @return array{templateId: string, typeId: string}
     */
    private function createUserRecoveryMailTemplate(Connection $connection): array
    {
        $typeId = Uuid::randomBytes();
        $connection->insert('mail_template_type', [
            'id' => $typeId,
            'technical_name' => 'user.recovery.request',
            'available_entities' => json_encode(['userRecovery' => 'user_recovery'], \JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt(),
        ]);

        $templateId = Uuid::randomBytes();
        $connection->insert('mail_template', [
            'id' => $templateId,
            'mail_template_type_id' => $typeId,
            'system_default' => 1,
            'was_modified_by_user' => 0,
            'created_at' => $this->createdAt(),
        ]);

        $translations = [
            Defaults::LANGUAGE_SYSTEM => [
                'typeName' => '用户密码恢复',
                'senderName' => 'Contena',
                'subject' => '重置您的 Contena 密码',
                'description' => '发送给请求恢复密码的后台用户。',
                'contentHtml' => <<<'HTML'
<p>{{ userRecovery.user.name }}，您好：</p>
<p>我们收到了重置您的 Contena 密码的请求。</p>
<p><a href="{{ resetUrl }}">重置密码</a></p>
<p>如果您没有提出此请求，请忽略此邮件。</p>
HTML,
                'contentPlain' => <<<'TEXT'
{{ userRecovery.user.name }}，您好：

我们收到了重置您的 Contena 密码的请求。
请访问以下链接重置密码：{{ resetUrl }}

如果您没有提出此请求，请忽略此邮件。
TEXT,
            ],
            $this->getEnGbLanguageId() => [
                'typeName' => 'User password recovery',
                'senderName' => 'Contena',
                'subject' => 'Reset your Contena password',
                'description' => 'Sent to an Administration user who requested a password reset.',
                'contentHtml' => <<<'HTML'
<p>Hello {{ userRecovery.user.name }},</p>
<p>We received a request to reset your Contena password.</p>
<p><a href="{{ resetUrl }}">Reset password</a></p>
<p>If you did not request this, you can ignore this email.</p>
HTML,
                'contentPlain' => <<<'TEXT'
Hello {{ userRecovery.user.name }},

We received a request to reset your Contena password.
Reset your password using this link: {{ resetUrl }}

If you did not request this, you can ignore this email.
TEXT,
            ],
        ];

        foreach ($translations as $languageId => $translation) {
            $languageId = Uuid::fromHexToBytes($languageId);

            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => $languageId,
                'name' => $translation['typeName'],
                'created_at' => $this->createdAt(),
            ]);

            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $languageId,
                'sender_name' => $translation['senderName'],
                'subject' => $translation['subject'],
                'description' => $translation['description'],
                'content_html' => $translation['contentHtml'],
                'content_plain' => $translation['contentPlain'],
                'created_at' => $this->createdAt(),
            ]);
        }

        return ['templateId' => $templateId, 'typeId' => $typeId];
    }

    private function createDefaultRules(Connection $connection): void
    {
        $this->createRule(
            $connection,
            self::WORKING_DAYS_RULE_ID,
            '工作日',
            '周一至周五',
            [
                ['id' => '019fc5b5ad1f7a659e3eea39f1000110', 'type' => 'orContainer', 'value' => null, 'parentId' => null, 'position' => 0],
                ['id' => '019fc5b5ad1f7a659e3eea39f1000111', 'type' => 'dayOfWeek', 'value' => ['operator' => '=', 'dayOfWeek' => 1], 'parentId' => '019fc5b5ad1f7a659e3eea39f1000110', 'position' => 1],
                ['id' => '019fc5b5ad1f7a659e3eea39f1000112', 'type' => 'dayOfWeek', 'value' => ['operator' => '=', 'dayOfWeek' => 2], 'parentId' => '019fc5b5ad1f7a659e3eea39f1000110', 'position' => 2],
                ['id' => '019fc5b5ad1f7a659e3eea39f1000113', 'type' => 'dayOfWeek', 'value' => ['operator' => '=', 'dayOfWeek' => 3], 'parentId' => '019fc5b5ad1f7a659e3eea39f1000110', 'position' => 3],
                ['id' => '019fc5b5ad1f7a659e3eea39f1000114', 'type' => 'dayOfWeek', 'value' => ['operator' => '=', 'dayOfWeek' => 4], 'parentId' => '019fc5b5ad1f7a659e3eea39f1000110', 'position' => 4],
                ['id' => '019fc5b5ad1f7a659e3eea39f1000115', 'type' => 'dayOfWeek', 'value' => ['operator' => '=', 'dayOfWeek' => 5], 'parentId' => '019fc5b5ad1f7a659e3eea39f1000110', 'position' => 5],
            ]
        );

        $this->createRule(
            $connection,
            self::WORKING_HOURS_RULE_ID,
            '工作时间',
            '每天 09:00 至 18:00',
            [
                ['id' => '019fc5b5ad1f7a659e3eea39f1000120', 'type' => 'timeRange', 'value' => ['fromTime' => '09:00', 'toTime' => '18:00', 'timezone' => null], 'parentId' => null, 'position' => 0],
            ]
        );

        $this->createRule(
            $connection,
            self::SYSTEM_LANGUAGE_RULE_ID,
            '系统语言',
            '当前上下文使用系统默认语言',
            [
                ['id' => '019fc5b5ad1f7a659e3eea39f1000130', 'type' => 'language', 'value' => ['operator' => '=', 'languageIds' => [Defaults::LANGUAGE_SYSTEM]], 'parentId' => null, 'position' => 0],
            ]
        );
    }

    /**
     * @param list<array{id: string, type: string, value: array<string, mixed>|null, parentId: string|null, position: int}> $conditions
     */
    private function createRule(
        Connection $connection,
        string $id,
        string $name,
        string $description,
        array $conditions
    ): void {
        $ruleId = Uuid::fromHexToBytes($id);
        $createdAt = $this->createdAt();
        $connection->insert('rule', [
            'id' => $ruleId,
            'name' => $name,
            'description' => $description,
            'priority' => 100,
            'created_at' => $createdAt,
        ]);

        foreach ($conditions as $condition) {
            $connection->insert('rule_condition', [
                'id' => Uuid::fromHexToBytes($condition['id']),
                'type' => $condition['type'],
                'rule_id' => $ruleId,
                'parent_id' => $condition['parentId'] === null ? null : Uuid::fromHexToBytes($condition['parentId']),
                'value' => $condition['value'] === null ? null : json_encode($condition['value'], \JSON_THROW_ON_ERROR),
                'position' => $condition['position'],
                'created_at' => $createdAt,
            ]);
        }
    }

    private function createUserRecoveryFlow(Connection $connection, string $mailTemplateId, string $mailTemplateTypeId): void
    {
        $flowId = Uuid::fromHexToBytes(self::USER_RECOVERY_FLOW_ID);
        $sequenceId = Uuid::fromHexToBytes(self::USER_RECOVERY_FLOW_SEQUENCE_ID);
        $createdAt = $this->createdAt();
        $connection->insert('flow', [
            'id' => $flowId,
            'name' => '用户密码恢复邮件',
            'description' => '用户请求重置密码时发送恢复邮件。',
            'event_name' => 'user.recovery.request',
            'priority' => 100,
            'active' => 1,
            'created_at' => $createdAt,
        ]);

        $connection->insert('flow_sequence', [
            'id' => $sequenceId,
            'flow_id' => $flowId,
            'action_name' => 'action.mail.send',
            'config' => json_encode([
                'recipient' => ['data' => [], 'type' => 'default'],
                'mailTemplateId' => Uuid::fromBytesToHex($mailTemplateId),
                'mailTemplateTypeId' => Uuid::fromBytesToHex($mailTemplateTypeId),
            ], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'display_group' => 1,
            'true_case' => 0,
            'created_at' => $createdAt,
        ]);

        $templateId = Uuid::fromHexToBytes(self::USER_RECOVERY_FLOW_TEMPLATE_ID);
        $connection->insert('flow_template', [
            'id' => $templateId,
            'name' => '用户密码恢复邮件',
            'config' => json_encode([
                'eventName' => 'user.recovery.request',
                'sequences' => [[
                    'id' => self::USER_RECOVERY_FLOW_SEQUENCE_ID,
                    'config' => [
                        'recipient' => ['data' => [], 'type' => 'default'],
                        'mailTemplateId' => Uuid::fromBytesToHex($mailTemplateId),
                        'mailTemplateTypeId' => Uuid::fromBytesToHex($mailTemplateTypeId),
                    ],
                    'ruleId' => null,
                    'parentId' => null,
                    'position' => 1,
                    'trueCase' => false,
                    'actionName' => 'action.mail.send',
                    'displayGroup' => 1,
                ]],
                'description' => '用户请求重置密码时发送恢复邮件。',
                'customFields' => null,
            ], \JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ]);
    }

    private function createSystemConfigOptions(Connection $connection): void
    {
        $options = [
            'core.basicInformation.activeCaptchasV2' => [
                'honeypot' => ['name' => 'Honeypot', 'isActive' => false],
                'basicCaptcha' => ['name' => 'basicCaptcha', 'isActive' => false],
                'googleReCaptchaV2' => [
                    'name' => 'googleReCaptchaV2',
                    'isActive' => false,
                    'config' => ['siteKey' => '', 'secretKey' => '', 'invisible' => false],
                ],
                'googleReCaptchaV3' => [
                    'name' => 'googleReCaptchaV3',
                    'isActive' => false,
                    'config' => ['siteKey' => '', 'secretKey' => '', 'thresholdScore' => 0.5],
                ],
            ],
            'core.basicInformation.siteName' => 'Contena',
            'core.basicInformation.useDefaultCookieConsent' => true,
            'core.sitemap.excludeLinkedBlogs' => false,
            'core.sitemap.sitemapRefreshStrategy' => '2',
            'core.sitemap.sitemapRefreshTime' => 3600,
            'core.userPermission.passwordMinLength' => 8,
        ];

        foreach ($options as $key => $value) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => $key,
                'configuration_value' => json_encode(['_value' => $value], \JSON_THROW_ON_ERROR),
                'created_at' => $this->createdAt(),
            ]);
        }
    }

    private function createDefaultMailHeaderFooter(Connection $connection): void
    {
        $headerFooterId = Uuid::randomBytes();
        $connection->insert('mail_header_footer', [
            'id' => $headerFooterId,
            'system_default' => 1,
            'created_at' => $this->createdAt(),
        ]);

        $translations = [
            Defaults::LANGUAGE_SYSTEM => [
                'name' => 'Contena 默认页眉和页脚',
                'description' => '用于系统邮件的默认页眉和页脚。',
                'headerHtml' => <<<'HTML'
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;background:#f4f7fb;font-family:Arial,sans-serif">
    <tr><td align="center" style="padding:24px 16px">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dfe5ec;border-radius:10px">
            <tr><td style="padding:20px 28px;color:#ffffff;background:#2563eb;border-radius:10px 10px 0 0;font-size:22px;font-weight:700">Contena</td></tr>
        </table>
    </td></tr>
</table>
HTML,
                'headerPlain' => "Contena\n=======\n\n",
                'footerHtml' => <<<'HTML'
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0 0;background:#f4f7fb;font-family:Arial,sans-serif">
    <tr><td align="center" style="padding:20px 16px;color:#64748b;font-size:12px;line-height:20px">
        这是一封由 Contena 自动发送的系统邮件，请勿直接回复。
    </td></tr>
</table>
HTML,
                'footerPlain' => "\n---\n这是一封由 Contena 自动发送的系统邮件，请勿直接回复。",
            ],
            $this->getEnGbLanguageId() => [
                'name' => 'Contena default header and footer',
                'description' => 'Default header and footer for system emails.',
                'headerHtml' => <<<'HTML'
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;background:#f4f7fb;font-family:Arial,sans-serif">
    <tr><td align="center" style="padding:24px 16px">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dfe5ec;border-radius:10px">
            <tr><td style="padding:20px 28px;color:#ffffff;background:#2563eb;border-radius:10px 10px 0 0;font-size:22px;font-weight:700">Contena</td></tr>
        </table>
    </td></tr>
</table>
HTML,
                'headerPlain' => "Contena\n=======\n\n",
                'footerHtml' => <<<'HTML'
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0 0;background:#f4f7fb;font-family:Arial,sans-serif">
    <tr><td align="center" style="padding:20px 16px;color:#64748b;font-size:12px;line-height:20px">
        This is an automated system email from Contena. Please do not reply.
    </td></tr>
</table>
HTML,
                'footerPlain' => "\n---\nThis is an automated system email from Contena. Please do not reply.",
            ],
        ];

        foreach ($translations as $languageId => $translation) {
            $languageId = Uuid::fromHexToBytes($languageId);

            $connection->insert('mail_header_footer_translation', [
                'mail_header_footer_id' => $headerFooterId,
                'language_id' => $languageId,
                'name' => $translation['name'],
                'description' => $translation['description'],
                'header_html' => $translation['headerHtml'],
                'header_plain' => $translation['headerPlain'],
                'footer_html' => $translation['footerHtml'],
                'footer_plain' => $translation['footerPlain'],
                'created_at' => $this->createdAt(),
            ]);
        }
    }

    private function ensureDefaultMediaThumbnailSize(Connection $connection): void
    {
        $connection->insert('media_thumbnail_size', [
            'id' => Uuid::randomBytes(),
            'width' => 200,
            'height' => 200,
            'created_at' => $this->createdAt(),
        ]);
    }

    private function getEnGbLanguageId(): string
    {
        return $this->enGbLanguageId ??= Uuid::randomHex();
    }

    private function createDefaultSnippetSets(Connection $connection): void
    {
        foreach ([
            ['name' => 'BASE zh-CN', 'base_file' => 'messages.zh', 'iso' => Defaults::DEFAULT_LOCALE],
            ['name' => 'BASE en-GB', 'base_file' => 'messages.en', 'iso' => 'en-GB'],
        ] as $snippetSet) {
            $connection->insert('snippet_set', [
                'id' => Uuid::randomBytes(),
                ...$snippetSet,
                'created_at' => $this->createdAt(),
            ]);
        }
    }

    private function createLanguage(Connection $connection): void
    {
        $zhLocaleId = Uuid::randomBytes();
        $enLocaleId = Uuid::randomBytes();
        $zhLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $enLanguageId = Uuid::fromHexToBytes($this->getEnGbLanguageId());
        $createdAt = $this->createdAt();

        $connection->insert('locale', ['id' => $zhLocaleId, 'code' => Defaults::DEFAULT_LOCALE, 'created_at' => $createdAt]);
        $connection->insert('locale', ['id' => $enLocaleId, 'code' => 'en-GB', 'created_at' => $createdAt]);

        $connection->insert('language', ['id' => $zhLanguageId, 'name' => '简体中文', 'locale_id' => $zhLocaleId, 'translation_code_id' => $zhLocaleId, 'active' => 1, 'created_at' => $createdAt]);
        $connection->insert('language', ['id' => $enLanguageId, 'name' => 'English', 'locale_id' => $enLocaleId, 'translation_code_id' => $enLocaleId, 'active' => 1, 'created_at' => $createdAt]);

        $connection->insert('locale_translation', ['locale_id' => $zhLocaleId, 'language_id' => $zhLanguageId, 'name' => '中文', 'territory' => '中国', 'created_at' => $createdAt]);
        $connection->insert('locale_translation', ['locale_id' => $zhLocaleId, 'language_id' => $enLanguageId, 'name' => 'Chinese', 'territory' => 'China', 'created_at' => $createdAt]);
        $connection->insert('locale_translation', ['locale_id' => $enLocaleId, 'language_id' => $zhLanguageId, 'name' => '英语', 'territory' => '英国', 'created_at' => $createdAt]);
        $connection->insert('locale_translation', ['locale_id' => $enLocaleId, 'language_id' => $enLanguageId, 'name' => 'English', 'territory' => 'United Kingdom', 'created_at' => $createdAt]);
    }

    private function createCountries(Connection $connection): void
    {
        $languageIds = [
            Defaults::DEFAULT_LOCALE => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'en-GB' => Uuid::fromHexToBytes($this->getEnGbLanguageId()),
        ];
        $createdAt = $this->createdAt();

        foreach (self::COUNTRIES as $country) {
            $countryId = Uuid::randomBytes();

            $connection->insert('country', [
                'id' => $countryId,
                'iso' => $country['iso'],
                'iso3' => $country['iso3'],
                'position' => $country['position'],
                'active' => 1,
                'created_at' => $createdAt,
            ]);

            foreach ($languageIds as $locale => $languageId) {
                $connection->insert('country_translation', [
                    'country_id' => $countryId,
                    'language_id' => $languageId,
                    'name' => $country[$locale],
                    'created_at' => $createdAt,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $region
     * @param list<string> $path
     * @param list<array{id: string, code: string}> $languages
     */
    private function insertChinaRegion(
        Connection $connection,
        string $countryId,
        array $region,
        ?string $parentId,
        array $path,
        int $position,
        array $languages,
    ): void {
        $code = (string) ($region['code'] ?? '');
        $name = (string) ($region['name'] ?? '');
        $type = (string) ($region['type'] ?? 'region');
        $translations = \is_array($region['translations'] ?? null) ? $region['translations'] : [];
        if ($code === '' || $name === '') {
            return;
        }

        $regionId = Uuid::fromHexToBytes(Hasher::hash('contena:region:CN:' . $code));
        $connection->insert('region', [
            'id' => $regionId,
            'country_id' => $countryId,
            'parent_id' => $parentId,
            'level' => \count($path) + 1,
            'type' => $type,
            'code' => $code,
            'path' => $path === [] ? null : '|' . implode('|', $path) . '|',
            'child_count' => \count($region['children'] ?? []),
            'position' => $position,
            'active' => 1,
            'created_at' => $this->createdAt(),
        ]);

        foreach ($languages as $language) {
            $connection->executeStatement(
                'INSERT IGNORE INTO `region_translation`
                    (`region_id`, `language_id`, `name`, `created_at`)
                 VALUES (:regionId, :languageId, :name, :createdAt)',
                [
                    'regionId' => $regionId,
                    'languageId' => $language['id'],
                    'name' => (string) ($translations[$language['code']] ?? $name),
                    'createdAt' => $this->createdAt(),
                ]
            );
        }

        $childPath = array_merge($path, [Uuid::fromBytesToHex($regionId)]);
        foreach (($region['children'] ?? []) as $childPosition => $child) {
            if (\is_array($child)) {
                $this->insertChinaRegion($connection, $countryId, $child, $regionId, $childPath, $childPosition + 1, $languages);
            }
        }
    }

    private function createDefaultMediaFolders(Connection $connection): void
    {
        $createdAt = $this->createdAt();

        foreach (self::DEFAULT_MEDIA_FOLDERS as $entity => $folderName) {
            $defaultFolderId = Uuid::randomBytes();
            $connection->insert('media_default_folder', [
                'id' => $defaultFolderId,
                'entity' => $entity,
                'created_at' => $createdAt,
            ]);

            $configurationId = Uuid::randomBytes();
            $connection->insert('media_folder_configuration', [
                'id' => $configurationId,
                'created_at' => $createdAt,
            ]);
            $connection->insert('media_folder', [
                'id' => Uuid::randomBytes(),
                'name' => $folderName,
                'default_folder_id' => $defaultFolderId,
                'media_folder_configuration_id' => $configurationId,
                'use_parent_configuration' => 0,
                'child_count' => 0,
                'created_at' => $createdAt,
            ]);
        }
    }

    private function createCoreGenderDictionary(Connection $connection): void
    {
        $createdAt = $this->createdAt();
        $dictionaryId = $this->ensureCoreGenderDictionary($connection, $createdAt);
        $itemIds = $this->ensureCoreGenderItems($connection, $dictionaryId, $createdAt);

        $this->ensureCoreGenderTranslations($connection, $dictionaryId, $itemIds, $createdAt);
    }

    private function ensureCoreGenderDictionary(Connection $connection, string $createdAt): string
    {
        $dictionaryId = Uuid::fromHexToBytes(self::CORE_GENDER_DICTIONARY_ID);
        $connection->insert('data_dictionary', [
            'id' => $dictionaryId,
            'technical_name' => DataDictionaryDefinition::CORE_GENDER,
            'active' => 1,
            'system_locked' => 1,
            'created_at' => $createdAt,
        ]);

        return $dictionaryId;
    }

    /**
     * @return array<string, string>
     */
    private function ensureCoreGenderItems(Connection $connection, string $dictionaryId, string $createdAt): array
    {
        $itemIds = [];

        foreach (self::CORE_GENDER_ITEMS as $code => $item) {
            $itemId = Uuid::fromHexToBytes($item['id']);
            $connection->insert('data_dictionary_item', [
                'id' => $itemId,
                'dictionary_id' => $dictionaryId,
                'code' => $code,
                'position' => $item['position'],
                'active' => 1,
                'system_locked' => 1,
                'created_at' => $createdAt,
            ]);

            $itemIds[$code] = $itemId;
        }

        return $itemIds;
    }

    /**
     * @param array<string, string> $itemIds
     */
    private function ensureCoreGenderTranslations(
        Connection $connection,
        string $dictionaryId,
        array $itemIds,
        string $createdAt
    ): void {
        $languages = $connection->fetchAllAssociative(
            'SELECT `language`.`id`, `locale`.`code`
             FROM `language`
             LEFT JOIN `locale` ON `locale`.`id` = `language`.`translation_code_id`'
        );

        foreach ($languages as $language) {
            $locale = $language['code'] === Defaults::DEFAULT_LOCALE ? Defaults::DEFAULT_LOCALE : 'en-GB';
            $languageId = $language['id'];

            $connection->executeStatement(
                'INSERT IGNORE INTO `data_dictionary_translation`
                    (`data_dictionary_id`, `language_id`, `label`, `description`, `created_at`)
                 VALUES (:dictionaryId, :languageId, :label, :description, :createdAt)',
                [
                    'dictionaryId' => $dictionaryId,
                    'languageId' => $languageId,
                    'label' => $locale === Defaults::DEFAULT_LOCALE ? '性别' : 'Gender',
                    'description' => $locale === Defaults::DEFAULT_LOCALE ? '用于性别值的系统数据字典。' : 'System dictionary for gender values.',
                    'createdAt' => $createdAt,
                ]
            );

            foreach (self::CORE_GENDER_ITEMS as $code => $item) {
                $connection->executeStatement(
                    'INSERT IGNORE INTO `data_dictionary_item_translation`
                        (`data_dictionary_item_id`, `language_id`, `label`, `created_at`)
                     VALUES (:itemId, :languageId, :label, :createdAt)',
                    [
                        'itemId' => $itemIds[$code],
                        'languageId' => $languageId,
                        'label' => $item[$locale],
                        'createdAt' => $createdAt,
                    ]
                );
            }
        }
    }

    private function createCoreRegionTypeDictionary(Connection $connection): void
    {
        $createdAt = $this->createdAt();
        $dictionaryId = Uuid::fromHexToBytes(self::CORE_REGION_TYPE_DICTIONARY_ID);
        $connection->insert('data_dictionary', [
            'id' => $dictionaryId,
            'technical_name' => DataDictionaryDefinition::CORE_REGION_TYPE,
            'active' => 1,
            'system_locked' => 1,
            'created_at' => $createdAt,
        ]);

        $itemIds = [];
        foreach (self::CORE_REGION_TYPE_ITEMS as $code => $item) {
            $itemId = Uuid::fromHexToBytes($item['id']);
            $connection->insert('data_dictionary_item', [
                'id' => $itemId,
                'dictionary_id' => $dictionaryId,
                'code' => $code,
                'position' => $item['position'],
                'active' => 1,
                'system_locked' => 1,
                'created_at' => $createdAt,
            ]);

            $itemIds[$code] = $itemId;
        }

        $languages = $connection->fetchAllAssociative(
            'SELECT `language`.`id`, `locale`.`code`
             FROM `language`
             LEFT JOIN `locale` ON `locale`.`id` = `language`.`translation_code_id`'
        );

        foreach ($languages as $language) {
            $locale = $language['code'] === Defaults::DEFAULT_LOCALE ? Defaults::DEFAULT_LOCALE : 'en-GB';
            $languageId = $language['id'];

            $connection->executeStatement(
                'INSERT IGNORE INTO `data_dictionary_translation`
                    (`data_dictionary_id`, `language_id`, `label`, `description`, `created_at`)
                 VALUES (:dictionaryId, :languageId, :label, :description, :createdAt)',
                [
                    'dictionaryId' => $dictionaryId,
                    'languageId' => $languageId,
                    'label' => $locale === Defaults::DEFAULT_LOCALE ? '行政区划类型' : 'Administrative region types',
                    'description' => $locale === Defaults::DEFAULT_LOCALE
                        ? '用于行政区划类型的系统数据字典。'
                        : 'System dictionary for administrative region types.',
                    'createdAt' => $createdAt,
                ]
            );

            foreach (self::CORE_REGION_TYPE_ITEMS as $code => $item) {
                $connection->executeStatement(
                    'INSERT IGNORE INTO `data_dictionary_item_translation`
                        (`data_dictionary_item_id`, `language_id`, `label`, `created_at`)
                     VALUES (:itemId, :languageId, :label, :createdAt)',
                    [
                        'itemId' => $itemIds[$code],
                        'languageId' => $languageId,
                        'label' => $item[$locale],
                        'createdAt' => $createdAt,
                    ]
                );
            }
        }
    }

    private function createUserNumberRange(Connection $connection): void
    {
        $typeId = Uuid::randomBytes();
        $connection->insert('number_range_type', [
            'id' => $typeId,
            'technical_name' => 'user',
            'global' => 1,
            'created_at' => $this->createdAt(),
        ]);

        $languages = $connection->fetchAllAssociative(
            'SELECT `locale`.`code`, `language`.`id`
             FROM `language`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
             WHERE `locale`.`code` IN (:zh, :en)',
            ['zh' => Defaults::DEFAULT_LOCALE, 'en' => 'en-GB']
        );

        foreach ($languages as $language) {
            $typeName = $language['code'] === Defaults::DEFAULT_LOCALE ? '用户' : 'User';
            $connection->insert('number_range_type_translation', [
                'number_range_type_id' => $typeId,
                'language_id' => $language['id'],
                'type_name' => $typeName,
                'created_at' => $this->createdAt(),
            ]);
        }

        $numberRangeId = Uuid::randomBytes();
        $connection->insert('number_range', [
            'id' => $numberRangeId,
            'type_id' => $typeId,
            'global' => 1,
            'pattern' => '{n}',
            'start' => 10000,
            'created_at' => $this->createdAt(),
        ]);

        foreach ($languages as $language) {
            $name = $language['code'] === Defaults::DEFAULT_LOCALE ? '用户编码' : 'User codes';
            $connection->insert('number_range_translation', [
                'number_range_id' => $numberRangeId,
                'language_id' => $language['id'],
                'name' => $name,
                'created_at' => $this->createdAt(),
            ]);
        }
    }

    private function createMemberNumberRange(Connection $connection): void
    {
        $typeId = Uuid::randomBytes();
        $connection->insert('number_range_type', [
            'id' => $typeId,
            'technical_name' => 'member',
            'global' => 0,
            'created_at' => $this->createdAt(),
        ]);

        $languages = $connection->fetchAllAssociative(
            'SELECT `locale`.`code`, `language`.`id`
             FROM `language`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
             WHERE `locale`.`code` IN (:zh, :en)',
            ['zh' => Defaults::DEFAULT_LOCALE, 'en' => 'en-GB']
        );

        foreach ($languages as $language) {
            $typeName = $language['code'] === Defaults::DEFAULT_LOCALE ? '会员' : 'Member';
            $connection->insert('number_range_type_translation', [
                'number_range_type_id' => $typeId,
                'language_id' => $language['id'],
                'type_name' => $typeName,
                'created_at' => $this->createdAt(),
            ]);
        }

        $numberRangeId = Uuid::randomBytes();
        $connection->insert('number_range', [
            'id' => $numberRangeId,
            'type_id' => $typeId,
            'global' => 1,
            'pattern' => '{n}',
            'start' => 10000,
            'created_at' => $this->createdAt(),
        ]);

        foreach ($languages as $language) {
            $name = $language['code'] === Defaults::DEFAULT_LOCALE ? '会员编码' : 'Member codes';
            $connection->insert('number_range_translation', [
                'number_range_id' => $numberRangeId,
                'language_id' => $language['id'],
                'name' => $name,
                'created_at' => $this->createdAt(),
            ]);
        }
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'zh-CN') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }

    private function createdAt(): string
    {
        return new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function seedOrganizationData(Connection $connection): void
    {
        $createdAt = $this->createdAt();
        $unitIds = [];

        foreach (self::DEFAULT_UNITS as $technicalName => $unit) {
            $unitId = Uuid::randomBytes();
            $unitIds[$technicalName] = $unitId;

            $connection->insert('organization_unit', [
                'id' => $unitId,
                'technical_name' => $technicalName,
                'position' => $unit['position'],
                'active' => 1,
                'created_at' => $createdAt,
            ]);

            $this->importTranslation('organization_unit_translation', new Translations(
                ['organization_unit_id' => $unitId, 'name' => $unit['zh-CN']],
                ['organization_unit_id' => $unitId, 'name' => $unit['en-GB']]
            ), $connection);
        }

        $organizationIds = [];

        foreach (self::DEFAULT_ORGANIZATIONS as $code => $organization) {
            $parentId = $organization['parent'] === null ? null : $organizationIds[$organization['parent']];

            $organizationId = Uuid::fromHexToBytes(Hasher::hash('contena:organization:' . $code));
            $organizationIds[$code] = $organizationId;

            $connection->insert('organization', [
                'id' => $organizationId,
                'parent_id' => $parentId,
                'organization_unit_id' => $unitIds[$organization['unit']],
                'level' => $parentId === null ? 1 : 2,
                'code' => $code,
                'path' => $parentId === null ? null : '|' . Uuid::fromBytesToHex($parentId) . '|',
                'child_count' => $code === 'HQ' ? 1 : 0,
                'position' => $organization['position'],
                'active' => 1,
                'created_at' => $createdAt,
            ]);

            $this->importTranslation('organization_translation', new Translations(
                ['organization_id' => $organizationId, 'name' => $organization['zh-CN']],
                ['organization_id' => $organizationId, 'name' => $organization['en-GB']]
            ), $connection);
        }

        $this->registerIndexer($connection, 'organization.indexer');
    }

    private function seedPositionData(Connection $connection): void
    {
        $createdAt = $this->createdAt();

        foreach (self::DEFAULT_POSITIONS as $code => $position) {
            $positionId = Uuid::fromHexToBytes(Hasher::hash('contena:position:' . $code));

            $connection->insert('position', [
                'id' => $positionId,
                'code' => $code,
                'position' => $position['position'],
                'active' => 1,
                'created_at' => $createdAt,
            ]);

            $this->importTranslation('position_translation', new Translations(
                ['position_id' => $positionId, 'name' => $position['zh-CN']],
                ['position_id' => $positionId, 'name' => $position['en-GB']]
            ), $connection);
        }
    }

    private function seedBlogSortingDefaults(Connection $connection): void
    {
        $sortings = [
            'score' => [100, true, [['_score', 'desc']], '最佳结果', 'Best results'],
            'published-desc' => [90, false, [['blog.releaseDate', 'desc']], '最新优先', 'Newest first'],
            'published-asc' => [80, false, [['blog.releaseDate', 'asc']], '最早优先', 'Oldest first'],
            'name-asc' => [70, false, [['blog.name', 'asc']], '标题 A-Z', 'Title A-Z'],
            'name-desc' => [60, false, [['blog.name', 'desc']], '标题 Z-A', 'Title Z-A'],
        ];
        $createdAt = $this->createdAt();
        $sortingIds = [];

        foreach ($sortings as $key => [$priority, $locked, $fieldConfig, $chineseLabel, $englishLabel]) {
            $id = Uuid::randomBytes();
            $sortingIds[$key] = $id;

            $fields = array_map(static fn (array $field): array => [
                'field' => $field[0],
                'order' => $field[1],
                'priority' => 1,
                'naturalSorting' => 0,
            ], $fieldConfig);
            $connection->insert('blog_sorting', [
                'id' => $id,
                'url_key' => $key,
                'priority' => $priority,
                'active' => 1,
                'fields' => json_encode($fields, \JSON_THROW_ON_ERROR),
                'locked' => (int) $locked,
                'created_at' => $createdAt,
            ]);

            $this->importTranslation('blog_sorting_translation', new Translations(
                ['blog_sorting_id' => $id, 'label' => $chineseLabel],
                ['blog_sorting_id' => $id, 'label' => $englishLabel]
            ), $connection);
        }

        $defaults = [
            'core.listing.defaultSorting' => 'published-desc',
            'core.listing.defaultSearchResultSorting' => 'score',
        ];
        foreach ($defaults as $configurationKey => $sortingKey) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => $configurationKey,
                'configuration_value' => json_encode(['_value' => Uuid::fromBytesToHex($sortingIds[$sortingKey])], \JSON_THROW_ON_ERROR),
                'created_at' => $createdAt,
            ]);
        }
    }

    private function createBlogSearchDefaults(Connection $connection): void
    {
        $languageIds = $connection->fetchFirstColumn('SELECT `id` FROM `language`');
        $fields = [
            ['name', 500, true, true],
            ['customSearchKeywords', 500, true, true],
            ['keywords', 250, true, false],
            ['description', 80, true, false],
            ['metaTitle', 80, true, false],
            ['metaDescription', 80, true, false],
        ];
        $createdAt = $this->createdAt();

        foreach ($languageIds as $languageId) {
            $configId = Uuid::randomBytes();
            $connection->insert('blog_search_config', [
                'id' => $configId,
                'language_id' => $languageId,
                'and_logic' => 0,
                'min_search_length' => 2,
                'excluded_terms' => '[]',
                'created_at' => $createdAt,
            ]);

            foreach ($fields as [$field, $ranking, $tokenize, $useExactSubfield]) {
                $connection->insert('blog_search_config_field', [
                    'id' => Uuid::randomBytes(),
                    'blog_search_config_id' => $configId,
                    'field' => $field,
                    'tokenize' => (int) $tokenize,
                    'searchable' => 1,
                    'use_exact_subfield' => (int) $useExactSubfield,
                    'ranking' => $ranking,
                    'created_at' => $createdAt,
                ]);
            }
        }
    }

    private function seedChannelData(Connection $connection): void
    {
        $languageId = $connection->fetchOne('SELECT `id` FROM `language` WHERE `id` = :id', [
            'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);
        $countryId = $connection->fetchOne('SELECT `id` FROM `country` WHERE `active` = 1 ORDER BY `position`, `created_at` LIMIT 1');
        $snippetSetId = $connection->fetchOne('SELECT `id` FROM `snippet_set` WHERE `iso` = :iso ORDER BY `created_at` LIMIT 1', [
            'iso' => Defaults::DEFAULT_LOCALE,
        ]);

        $createdAt = $this->createdAt();
        $memberGroupId = Uuid::fromHexToBytes(self::DEFAULT_MEMBER_GROUP_ID);
        $navigationCategoryId = $this->createDefaultNavigationCategory($connection, $createdAt);

        $connection->insert('member_group', [
            'id' => $memberGroupId,
            'created_at' => $createdAt,
        ]);

        $this->importTranslation('member_group_translation', new Translations(
            ['member_group_id' => $memberGroupId, 'name' => '默认会员组'],
            ['member_group_id' => $memberGroupId, 'name' => 'Default member group']
        ), $connection);

        $mailHeaderFooterId = $connection->fetchOne('SELECT `id` FROM `mail_header_footer` WHERE `system_default` = 1 ORDER BY `created_at` LIMIT 1');

        $this->createChannelTypeData($connection, Defaults::CHANNEL_TYPE_API, 'API', 'API', '仅提供 API 的渠道', 'API-only channel', 'regular-rocket', $createdAt);
        $this->createChannelTypeData($connection, Defaults::CHANNEL_TYPE_WEB, 'Web', 'Web', '提供 HTML 页面的渠道', 'Channel with HTML pages', 'regular-globe', $createdAt);

        $this->createDefaultChannel($connection, Defaults::CHANNEL_TYPE_API, 'API', 'API', 'default.api', null, $languageId, $countryId, $snippetSetId, $memberGroupId, $navigationCategoryId, $createdAt);
        $webChannelId = $this->createDefaultChannel(
            $connection,
            Defaults::CHANNEL_TYPE_WEB,
            'Web',
            'Web',
            (string) EnvironmentHelper::getVariable('APP_URL', 'http://localhost'),
            $mailHeaderFooterId,
            $languageId,
            $countryId,
            $snippetSetId,
            $memberGroupId,
            $navigationCategoryId,
            $createdAt
        );

        $this->createDefaultContent($connection, $webChannelId, $navigationCategoryId, $createdAt);

        $this->createDefaultSeoUrlTemplates($connection, $createdAt);
    }

    private function createDefaultSeoUrlTemplates(Connection $connection, string $createdAt): void
    {
        $templates = [
            [
                'route_name' => 'frontend.blog.detail.page',
                'entity_name' => 'blog',
                'template' => '{{ blog.translated.name }}',
                'is_headless' => 0,
            ],
            [
                'route_name' => 'frontend.navigation.page',
                'entity_name' => 'category',
                'template' => '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}',
                'is_headless' => 0,
            ],
            [
                'route_name' => 'frontend.landing.page',
                'entity_name' => 'landing_page',
                'template' => '{{ landingPage.translated.url }}',
                'is_headless' => 0,
            ],
            [
                'route_name' => 'channel-api.blog.detail',
                'entity_name' => 'blog',
                'template' => '{{ blog.translated.name }}',
                'is_headless' => 1,
            ],
            [
                'route_name' => 'channel-api.category.detail',
                'entity_name' => 'category',
                'template' => '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}',
                'is_headless' => 1,
            ],
            [
                'route_name' => 'channel-api.landing-page.detail',
                'entity_name' => 'landing_page',
                'template' => '{{ landingPage.translated.url }}',
                'is_headless' => 1,
            ],
        ];

        foreach ($templates as $template) {
            $connection->insert('seo_url_template', [
                'id' => Uuid::randomBytes(),
                'channel_id' => null,
                'route_name' => $template['route_name'],
                'entity_name' => $template['entity_name'],
                'template' => $template['template'],
                'is_headless' => $template['is_headless'],
                'created_at' => $createdAt,
            ]);
        }
    }

    private function createDefaultNavigationCategory(Connection $connection, string $createdAt): string
    {
        $categoryId = Uuid::fromHexToBytes(self::DEFAULT_NAVIGATION_CATEGORY_ID);
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('category', [
            'id' => $categoryId,
            'version_id' => $versionId,
            'type' => 'page',
            'created_at' => $createdAt,
        ]);

        $this->importTranslation('category_translation', new Translations(
            ['category_id' => $categoryId, 'category_version_id' => $versionId, 'name' => '内容', 'breadcrumb' => json_encode(['内容'], \JSON_THROW_ON_ERROR)],
            ['category_id' => $categoryId, 'category_version_id' => $versionId, 'name' => 'Content', 'breadcrumb' => json_encode(['Content'], \JSON_THROW_ON_ERROR)]
        ), $connection);

        return $categoryId;
    }

    private function createChannelTypeData(
        Connection $connection,
        string $typeId,
        string $chineseName,
        string $englishName,
        string $chineseDescription,
        string $englishDescription,
        string $iconName,
        string $createdAt
    ): void {
        $typeId = Uuid::fromHexToBytes($typeId);

        $connection->insert('channel_type', [
            'id' => $typeId,
            'icon_name' => $iconName,
            'created_at' => $createdAt,
        ]);

        $this->importTranslation('channel_type_translation', new Translations(
            ['channel_type_id' => $typeId, 'name' => $chineseName, 'manufacturer' => 'Contena', 'description' => $chineseDescription],
            ['channel_type_id' => $typeId, 'name' => $englishName, 'manufacturer' => 'Contena', 'description' => $englishDescription]
        ), $connection);
    }

    private function createDefaultChannel(
        Connection $connection,
        string $typeId,
        string $chineseName,
        string $englishName,
        string $domain,
        ?string $mailHeaderFooterId,
        string $languageId,
        string $countryId,
        string $snippetSetId,
        string $memberGroupId,
        string $navigationCategoryId,
        string $createdAt
    ): string {
        $typeId = Uuid::fromHexToBytes($typeId);
        $channelId = $typeId === Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_API)
            ? Uuid::fromHexToBytes(self::DEFAULT_API_CHANNEL_ID)
            : Uuid::fromHexToBytes(self::DEFAULT_WEB_CHANNEL_ID);

        $connection->insert('channel', [
            'id' => $channelId,
            'type_id' => $typeId,
            'access_key' => AccessKeyHelper::generateAccessKey('channel'),
            'language_id' => $languageId,
            'country_id' => $countryId,
            'member_group_id' => $memberGroupId,
            'navigation_category_id' => $navigationCategoryId,
            'navigation_category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'mail_header_footer_id' => $mailHeaderFooterId,
            'business_time_zone' => Defaults::DEFAULT_TIME_ZONE,
            'created_at' => $createdAt,
        ]);

        $translationWriteResult = $this->importTranslation('channel_translation', new Translations(
            ['channel_id' => $channelId, 'name' => $chineseName],
            ['channel_id' => $channelId, 'name' => $englishName]
        ), $connection);

        foreach (array_merge($translationWriteResult->getChineseLanguages(), $translationWriteResult->getEnglishLanguages()) as $translationLanguageId) {
            $translationLanguageId = Uuid::fromHexToBytes($translationLanguageId);

            $connection->insert('channel_language', ['channel_id' => $channelId, 'language_id' => $translationLanguageId]);
        }

        $connection->insert('channel_country', ['channel_id' => $channelId, 'country_id' => $countryId]);

        $connection->insert('channel_domain', [
            'id' => Uuid::randomBytes(),
            'channel_id' => $channelId,
            'language_id' => $languageId,
            'url' => $domain,
            'snippet_set_id' => $snippetSetId,
            'created_at' => $createdAt,
        ]);

        return $channelId;
    }

    private function createDefaultContent(
        Connection $connection,
        string $channelId,
        string $navigationCategoryId,
        string $createdAt
    ): void {
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $blogCategoryId = Uuid::fromHexToBytes(self::DEFAULT_BLOG_CATEGORY_ID);
        $landingPageCategoryId = Uuid::fromHexToBytes(self::DEFAULT_LANDING_PAGE_CATEGORY_ID);
        $blogId = Uuid::fromHexToBytes(self::DEFAULT_BLOG_ID);
        $landingPageId = Uuid::fromHexToBytes(self::DEFAULT_LANDING_PAGE_ID);

        $this->createDefaultContentLayouts($connection, $createdAt);
        $this->createNavigationChild(
            $connection,
            $blogCategoryId,
            $navigationCategoryId,
            null,
            new Translations(
                ['name' => '博客', 'breadcrumb' => json_encode(['内容', '博客'], \JSON_THROW_ON_ERROR), 'internal_link' => null, 'link_type' => null],
                ['name' => 'Blog', 'breadcrumb' => json_encode(['Content', 'Blog'], \JSON_THROW_ON_ERROR), 'internal_link' => null, 'link_type' => null]
            ),
            null,
            null,
            $createdAt
        );
        $this->createDefaultLandingPage($connection, $landingPageId, $channelId, $createdAt);
        $this->createNavigationChild(
            $connection,
            $landingPageCategoryId,
            $navigationCategoryId,
            $blogCategoryId,
            new Translations(
                ['name' => '关于', 'breadcrumb' => json_encode(['内容', '关于'], \JSON_THROW_ON_ERROR), 'internal_link' => $landingPageId, 'link_type' => 'landing_page'],
                ['name' => 'About', 'breadcrumb' => json_encode(['Content', 'About'], \JSON_THROW_ON_ERROR), 'internal_link' => $landingPageId, 'link_type' => 'landing_page']
            ),
            'landing_page',
            $landingPageId,
            $createdAt
        );
        $connection->update('category', ['child_count' => 2], [
            'id' => $navigationCategoryId,
            'version_id' => $versionId,
        ]);

        $connection->insert('blog', [
            'id' => $blogId,
            'version_id' => $versionId,
            'active' => 1,
            'category_tree' => json_encode([self::DEFAULT_NAVIGATION_CATEGORY_ID, self::DEFAULT_BLOG_CATEGORY_ID], \JSON_THROW_ON_ERROR),
            'category_ids' => json_encode([self::DEFAULT_BLOG_CATEGORY_ID], \JSON_THROW_ON_ERROR),
            'tag_ids' => '[]',
            'release_date' => $createdAt,
            'created_at' => $createdAt,
        ]);

        $this->importTranslation('blog_translation', new Translations(
            ['blog_id' => $blogId, 'blog_version_id' => $versionId, 'name' => '欢迎使用 Contena', 'meta_title' => '欢迎使用 Contena', 'meta_description' => '用于发布结构化数字体验的灵活平台。'],
            ['blog_id' => $blogId, 'blog_version_id' => $versionId, 'name' => 'Welcome to Contena', 'meta_title' => 'Welcome to Contena', 'meta_description' => 'A flexible platform for publishing structured digital experiences.']
        ), $connection);

        $connection->insert('blog_visibility', [
            'id' => Uuid::randomBytes(),
            'blog_id' => $blogId,
            'blog_version_id' => $versionId,
            'channel_id' => $channelId,
            'visibility' => 30,
            'created_at' => $createdAt,
        ]);
        $connection->insert('blog_category', [
            'blog_id' => $blogId,
            'blog_version_id' => $versionId,
            'category_id' => $blogCategoryId,
            'category_version_id' => $versionId,
        ]);
        $connection->insert('blog_category_tree', [
            'blog_id' => $blogId,
            'blog_version_id' => $versionId,
            'category_id' => $navigationCategoryId,
            'category_version_id' => $versionId,
        ]);
        $connection->insert('blog_category_tree', [
            'blog_id' => $blogId,
            'blog_version_id' => $versionId,
            'category_id' => $blogCategoryId,
            'category_version_id' => $versionId,
        ]);
        $connection->insert('blog_main_category', [
            'id' => Uuid::randomBytes(),
            'blog_id' => $blogId,
            'blog_version_id' => $versionId,
            'category_id' => $blogCategoryId,
            'category_version_id' => $versionId,
            'channel_id' => $channelId,
            'created_at' => $createdAt,
        ]);

        $this->createContentLayoutAssignment($connection, 'blog', $blogId, $channelId, self::DEFAULT_BLOG_LAYOUT_ID, $createdAt);
        $this->createContentLayoutAssignment($connection, 'category', $navigationCategoryId, $channelId, self::DEFAULT_CATEGORY_LAYOUT_ID, $createdAt);
        $this->createContentLayoutAssignment($connection, 'category', $blogCategoryId, $channelId, self::DEFAULT_CATEGORY_LAYOUT_ID, $createdAt);
        $this->createContentLayoutAssignment($connection, 'landing_page', $landingPageId, $channelId, self::DEFAULT_LANDING_PAGE_LAYOUT_ID, $createdAt);
    }

    private function createDefaultContentLayouts(Connection $connection, string $createdAt): void
    {
        $layouts = [
            [
                'id' => self::DEFAULT_BLOG_LAYOUT_ID,
                'name' => 'Default blog content',
                'root_source' => 'blog',
                'layout' => [[
                    'id' => 'blog-content',
                    'component' => 'CT:Content:Text',
                    'properties' => [
                        'text' => '<h1>Welcome to Contena</h1><p>Edit this layout in Experience Studio.</p>',
                    ],
                ]],
            ],
            [
                'id' => self::DEFAULT_CATEGORY_LAYOUT_ID,
                'name' => 'Default category content',
                'root_source' => 'category',
                'layout' => [[
                    'id' => 'blog-listing',
                    'component' => 'CT:Blog:Listing',
                    'properties' => [
                        'navigationId' => '{{categoryId}}',
                        'limit' => 24,
                        'page' => 1,
                    ],
                    'dataRequirements' => [
                        'listing' => [
                            'key' => 'listing',
                            'source' => 'blog_listing',
                            'config' => ['property' => 'navigationId'],
                        ],
                    ],
                ]],
            ],
            [
                'id' => self::DEFAULT_LANDING_PAGE_LAYOUT_ID,
                'name' => 'Default landing page content',
                'root_source' => 'landing_page',
                'layout' => [[
                    'id' => 'landing-page-content',
                    'component' => 'CT:Content:Text',
                    'properties' => [
                        'text' => '<h1>About Contena</h1><p>Edit this layout in Experience Studio.</p>',
                    ],
                ]],
            ],
        ];

        foreach ($layouts as $layout) {
            $connection->insert('content_layout', [
                'id' => Uuid::fromHexToBytes($layout['id']),
                'name' => $layout['name'],
                'version' => '1.0.0',
                'layout' => json_encode($layout['layout'], \JSON_THROW_ON_ERROR),
                'root_source' => $layout['root_source'],
                'created_at' => $createdAt,
            ]);
        }
    }

    private function createNavigationChild(
        Connection $connection,
        string $categoryId,
        string $parentId,
        ?string $afterCategoryId,
        Translations $translations,
        ?string $linkType,
        ?string $internalLink,
        string $createdAt
    ): void {
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('category', [
            'id' => $categoryId,
            'version_id' => $versionId,
            'parent_id' => $parentId,
            'parent_version_id' => $versionId,
            'path' => '|' . Uuid::fromBytesToHex($parentId) . '|',
            'after_category_id' => $afterCategoryId,
            'after_category_version_id' => $afterCategoryId === null ? null : $versionId,
            'level' => 2,
            'type' => $linkType === null ? 'page' : 'link',
            'created_at' => $createdAt,
        ]);

        $this->importTranslation('category_translation', new Translations(
            array_merge(['category_id' => $categoryId, 'category_version_id' => $versionId], $translations->getChinese()),
            array_merge(['category_id' => $categoryId, 'category_version_id' => $versionId], $translations->getEnglish())
        ), $connection);
    }

    private function createDefaultLandingPage(
        Connection $connection,
        string $landingPageId,
        string $channelId,
        string $createdAt
    ): void {
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $connection->insert('landing_page', [
            'id' => $landingPageId,
            'version_id' => $versionId,
            'active' => 1,
            'created_at' => $createdAt,
        ]);

        $this->importTranslation('landing_page_translation', new Translations(
            ['landing_page_id' => $landingPageId, 'landing_page_version_id' => $versionId, 'name' => '关于 Contena', 'url' => 'about', 'meta_title' => '关于 Contena', 'meta_description' => '了解更多关于 Contena 的信息。'],
            ['landing_page_id' => $landingPageId, 'landing_page_version_id' => $versionId, 'name' => 'About Contena', 'url' => 'about', 'meta_title' => 'About Contena', 'meta_description' => 'Learn more about Contena.']
        ), $connection);

        $connection->insert('landing_page_channel', [
            'landing_page_id' => $landingPageId,
            'landing_page_version_id' => $versionId,
            'channel_id' => $channelId,
        ]);
    }

    private function createContentLayoutAssignment(
        Connection $connection,
        string $entity,
        string $entityId,
        string $channelId,
        string $contentLayoutId,
        string $createdAt
    ): void {
        $table = $entity . '_content_layout';
        $entityIdField = $entity . '_id';

        $connection->insert($table, [
            'id' => Uuid::randomBytes(),
            $entityIdField => $entityId,
            'channel_id' => $channelId,
            'content_layout_id' => Uuid::fromHexToBytes($contentLayoutId),
            'created_at' => $createdAt,
        ]);
    }
}
