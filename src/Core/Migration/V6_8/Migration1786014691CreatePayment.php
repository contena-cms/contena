<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Migration\Traits\ImportTranslationsTrait;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1786014691CreatePayment extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1786014691;
    }

    public function update(Connection $connection): void
    {
        $this->createTables($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createTables(Connection $connection): void
    {
        // payment_app
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_app` (
    `tenant_id`  BINARY(16)                              NULL,
    `id` BINARY(16) NOT NULL,
    `app_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Business application code, e.g. biz_app_001',
    `app_secret` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Application secret (encrypted at rest)',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_app.tenant_id` (`tenant_id`),
    UNIQUE `uniq.payment_app.app_code` (`app_code`),
    CONSTRAINT `fk.payment_app.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_app_translation
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_app_translation` (
    `payment_app_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `tenant_id` BINARY(16) NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`payment_app_id`, `language_id`),
    KEY `idx.payment_app_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `json.payment_app_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_app_translation.app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_app_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_app_translation.tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_channel
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_channel` (
    `id` BINARY(16) NOT NULL,
    `code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Channel code, e.g. alipay / wechat / paypal / stripe',
    `config_schema` JSON NULL COMMENT 'Declarative channel configuration schema used for validation and administration rendering',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status',
    `sort` INT NOT NULL DEFAULT 0 COMMENT 'Sort order',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE `uniq.payment_channel.code` (`code`),
    CONSTRAINT `json.payment_channel.config_schema` CHECK (JSON_VALID(`config_schema`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_channel_translation
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_channel_translation` (
    `payment_channel_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`payment_channel_id`, `language_id`),
    CONSTRAINT `json.payment_channel_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_channel_translation.channel_id` FOREIGN KEY (`payment_channel_id`) REFERENCES `payment_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_channel_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_channel_method
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_channel_method` (
    `id` BINARY(16) NOT NULL,
    `channel_id` BINARY(16) NOT NULL,
    `method_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Payment method code, e.g. h5 / app / mini_program / jsapi / native / page / face / card / paypal_web',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status',
    `sort` INT NOT NULL DEFAULT 0 COMMENT 'Sort order',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE `uniq.payment_channel_method.channel_method` (`channel_id`, `method_code`),
    CONSTRAINT `fk.payment_channel_method.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `payment_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_channel_method_translation
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_channel_method_translation` (
    `payment_channel_method_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`payment_channel_method_id`, `language_id`),
    CONSTRAINT `json.payment_channel_method_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_channel_method_translation.method_id` FOREIGN KEY (`payment_channel_method_id`) REFERENCES `payment_channel_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_channel_method_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_app_channel_method
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_app_channel_method` (
    `id` BINARY(16) NOT NULL,
    `tenant_id` BINARY(16) NULL,
    `payment_app_id` BINARY(16) NOT NULL,
    `channel_method_id` BINARY(16) NOT NULL,
    `config` JSON NULL COMMENT 'App-level method parameters (e.g. limits)',
    `sort` INT NOT NULL DEFAULT 0 COMMENT 'Sort order',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status',
    `rule_id` BINARY(16) NULL COMMENT 'Availability rule ID (method is only available for the app when the rule matches)',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE `uniq.payment_app_channel_method.app_method` (`payment_app_id`, `channel_method_id`),
    KEY `idx.payment_app_channel_method.tenant_id` (`tenant_id`),
    KEY `idx.payment_app_channel_method.rule_id` (`rule_id`),
    CONSTRAINT `json.payment_app_channel_method.config` CHECK (JSON_VALID(`config`)),
    CONSTRAINT `json.payment_app_channel_method.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_app_channel_method.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_app_channel_method.channel_method_id` FOREIGN KEY (`channel_method_id`) REFERENCES `payment_channel_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_app_channel_method.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_app_channel_method.tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_channel_config
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_channel_config` (
    `id` BINARY(16) NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `payment_app_id` BINARY(16) NULL COMMENT 'Owning app; NULL = platform unified collection config shared by all apps',
    `channel_id` BINARY(16) NOT NULL,
    `config` JSON NULL COMMENT 'Channel-specific parameters defined by the channel adapter (account identity, app id, keys, certificates, endpoints)',
    `rule_id` BINARY(16) NULL COMMENT 'Availability rule ID (config is only usable when the rule matches)',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Status',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_channel_config.tenant_id` (`tenant_id`),
    UNIQUE `uniq.payment_channel_config.app_channel` (`tenant_id`, `payment_app_id`, `channel_id`),
    KEY `idx.payment_channel_config.rule_id` (`rule_id`),
    CONSTRAINT `json.payment_channel_config.config` CHECK (JSON_VALID(`config`)),
    CONSTRAINT `json.payment_channel_config.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_channel_config.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_channel_config.channel_id` FOREIGN KEY (`channel_id`) REFERENCES `payment_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_channel_config.rule_id` FOREIGN KEY (`rule_id`) REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_channel_config.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_recurring (periodic recurring agreement)
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_recurring` (
    `id` BINARY(16) NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `payment_app_id` BINARY(16) NOT NULL COMMENT 'Initiating app ID',
    `recurring_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Platform agreement no',
    `external_recurring_no` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'External agreement no (merchant sign no, idempotency key)',
    `channel_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Channel code',
    `channel_config_id` BINARY(16) NOT NULL COMMENT 'Routed channel configuration (immutable)',
    `channel_recurring_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Primary channel credential snapshot (e.g. alipay agreement_no, wechat contract_id)',
    `channel_params` JSON NULL COMMENT 'Channel-specific credential structure (e.g. stripe customer/payment_method/mandate ids)',
    `channel_extra` JSON NULL COMMENT 'Per-subscription channel parameters',
    `notify_url` VARCHAR(2048) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Notification URL of the external system',
    `return_url` VARCHAR(2048) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Browser return URL after signing',
    `response_data` JSON NULL COMMENT 'Provider response snapshot',
    `result_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Provider result code',
    `result_message` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Provider result message',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT 'Status: 0=pending, 1=signed, 2=unsigned, 3=failed',
    `sign_time` DATETIME(3) NULL COMMENT 'Sign time',
    `expire_time` DATETIME(3) NULL COMMENT 'Authorization expiry time',
    `period_type` VARCHAR(16) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Subscription period type: DAY / MONTH',
    `period` INT NULL COMMENT 'Period interval',
    `execute_time` DATETIME(3) NULL COMMENT 'Next deduction time',
    `single_amount` BIGINT UNSIGNED NULL COMMENT 'Max single deduction amount (cents)',
    `total_amount` BIGINT UNSIGNED NULL COMMENT 'Total deductible amount (cents)',
    `total_payments` INT NULL COMMENT 'Total deduction count',
    `version` INT NOT NULL DEFAULT 0 COMMENT 'Optimistic lock version',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_recurring.tenant_id` (`tenant_id`),
    UNIQUE `uniq.payment_recurring.agreement_no` (`tenant_id`, `recurring_no`),
    UNIQUE `uniq.payment_recurring.app_external_recurring_no` (`tenant_id`, `payment_app_id`, `external_recurring_no`),
    KEY `idx.payment_recurring.payment_app_id` (`payment_app_id`),
    KEY `idx.payment_recurring.channel_config_id` (`channel_config_id`),
    CONSTRAINT `json.payment_recurring.channel_params` CHECK (JSON_VALID(`channel_params`)),
    CONSTRAINT `json.payment_recurring.channel_extra` CHECK (JSON_VALID(`channel_extra`)),
    CONSTRAINT `json.payment_recurring.response_data` CHECK (JSON_VALID(`response_data`)),
    CONSTRAINT `json.payment_recurring.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_recurring.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_recurring.channel_config_id` FOREIGN KEY (`channel_config_id`) REFERENCES `payment_channel_config` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_recurring.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_order
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_order` (
    `id` BINARY(16) NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `payment_app_id` BINARY(16) NOT NULL,
    `order_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Platform order no',
    `external_order_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'External system order no (idempotency key per app)',
    `amount` BIGINT UNSIGNED NOT NULL COMMENT 'Amount (in cents)',
    `refunded_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Refunded amount (kept consistent with refund status changes in the same transaction and lock)',
    `currency_code` VARCHAR(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY' COMMENT 'Currency code',
    `channel_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Channel code (immutable snapshot, no FK)',
    `method_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Payment method code (immutable snapshot, no FK)',
    `device_type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Device type',
    `subject` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Subject',
    `client_ip` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Payer client IP (required by some channels, e.g. WeChat H5)',
    `channel_extra` JSON NULL COMMENT 'Per-order channel parameters (e.g. WeChat openid, scene info)',
    `recurring_id` BINARY(16) NULL COMMENT 'Recurring agreement ID (for periodic deduction orders)',
    `notify_url` VARCHAR(2048) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Notify URL of the external system (copied into outbound notify records)',
    `return_url` VARCHAR(2048) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Browser return URL after payment',
    `state_id` BINARY(16) NOT NULL COMMENT 'State ID',
    `close_reason` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Close reason',
    `channel_trade_no` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Channel transaction no',
    `channel_config_id` BINARY(16) NOT NULL COMMENT 'Routed channel config ID (account identity source; config rows with orders must not be deleted)',
    `primary_transaction_id` BINARY(16) NULL COMMENT 'Latest order transaction ID',
    `success_time` DATETIME(3) NULL COMMENT 'Success time (channel confirmed the payment)',
    `expire_time` DATETIME(3) NULL COMMENT 'Expiration time',
    `version` INT NOT NULL DEFAULT 0 COMMENT 'Optimistic lock version',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_order.tenant_id` (`tenant_id`),
    UNIQUE `uniq.payment_order.order_no` (`tenant_id`, `order_no`),
    UNIQUE `uniq.payment_order.app_external_order_no` (`tenant_id`, `payment_app_id`, `external_order_no`),
    KEY `idx.payment_order.channel_trade_no` (`channel_trade_no`),
    KEY `idx.payment_order.channel_created` (`channel_code`, `created_at`),
    KEY `idx.payment_order.state_expire_time` (`state_id`, `expire_time`),
    KEY `idx.payment_order.channel_config_id` (`channel_config_id`),
    KEY `idx.payment_order.primary_transaction_id` (`primary_transaction_id`),
    KEY `idx.payment_order.channel_method` (`channel_code`, `method_code`),
    CONSTRAINT `chk.payment_order.amount_positive` CHECK (`amount` > 0),
    CONSTRAINT `chk.payment_order.refunded_amount_not_negative` CHECK (`refunded_amount` >= 0),
    CONSTRAINT `chk.payment_order.refunded_amount_not_exceed` CHECK (`refunded_amount` <= `amount`),
    CONSTRAINT `json.payment_order.channel_extra` CHECK (JSON_VALID(`channel_extra`)),
    CONSTRAINT `fk.payment_order.recurring_id` FOREIGN KEY (`recurring_id`) REFERENCES `payment_recurring` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `json.payment_order.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_order.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_order.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_order.channel_config_id` FOREIGN KEY (`channel_config_id`) REFERENCES `payment_channel_config` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_order.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_refund
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_refund` (
    `id` BINARY(16) NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `refund_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Refund no',
    `order_id` BINARY(16) NOT NULL COMMENT 'Order ID',
    `external_refund_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'External system refund no (idempotency key)',
    `refund_amount` BIGINT UNSIGNED NOT NULL COMMENT 'Refund amount (order currency)',
    `channel_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Channel code (redundant snapshot)',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT 'Status: 0=created, 1=processing, 2=succeeded, 3=failed',
    `channel_refund_no` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Channel refund no',
    `success_time` DATETIME(3) NULL COMMENT 'Success time',
    `reason` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Reason',
    `response_data` JSON NULL COMMENT 'Provider response data',
    `result_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Provider result code',
    `result_message` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Provider result message',
    `version` INT NOT NULL DEFAULT 0 COMMENT 'Optimistic lock version',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_refund.tenant_id` (`tenant_id`),
    UNIQUE `uniq.payment_refund.refund_no` (`tenant_id`, `refund_no`),
    UNIQUE `uniq.payment_refund.order_external_refund_no` (`tenant_id`, `order_id`, `external_refund_no`),
    UNIQUE `uniq.payment_refund.channel_refund` (`tenant_id`, `channel_code`, `channel_refund_no`),
    KEY `idx.payment_refund.order_id` (`order_id`),
    CONSTRAINT `chk.payment_refund.amount_positive` CHECK (`refund_amount` > 0),
    CONSTRAINT `json.payment_refund.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_refund.order_id` FOREIGN KEY (`order_id`) REFERENCES `payment_order` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_refund.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_transfer (fund transfer order)
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_transfer` (
    `id` BINARY(16) NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `payment_app_id` BINARY(16) NOT NULL COMMENT 'Initiating app ID',
    `transfer_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Platform transfer no',
    `external_transfer_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'External system transfer no (idempotency key)',
    `amount` BIGINT UNSIGNED NOT NULL COMMENT 'Transfer amount (order currency)',
    `currency_code` CHAR(3) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Currency code',
    `channel_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Channel code',
    `channel_config_id` BINARY(16) NOT NULL COMMENT 'Routed channel configuration (immutable)',
    `state_id` BINARY(16) NOT NULL COMMENT 'State ID',
    `payee` VARCHAR(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Payee account',
    `payee_name` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Payee real name',
    `remark` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Remark',
    `notify_url` VARCHAR(2048) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Notification URL of the external system',
    `channel_extra` JSON NULL COMMENT 'Per-transfer channel parameters',
    `channel_order_id` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Channel transfer order no',
    `channel_status` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Channel transfer status snapshot',
    `response_data` JSON NULL COMMENT 'Provider response snapshot',
    `result_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Provider result code',
    `result_message` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Provider result message',
    `success_time` DATETIME(3) NULL COMMENT 'Success time',
    `version` INT NOT NULL DEFAULT 0 COMMENT 'Optimistic lock version',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_transfer.tenant_id` (`tenant_id`),
    UNIQUE `uniq.payment_transfer.transfer_no` (`tenant_id`, `transfer_no`),
    UNIQUE `uniq.payment_transfer.app_external_transfer_no` (`tenant_id`, `payment_app_id`, `external_transfer_no`),
    KEY `idx.payment_transfer.payment_app_id` (`payment_app_id`),
    KEY `idx.payment_transfer.channel_config_id` (`channel_config_id`),
    CONSTRAINT `chk.payment_transfer.amount_positive` CHECK (`amount` > 0),
    CONSTRAINT `json.payment_transfer.channel_extra` CHECK (JSON_VALID(`channel_extra`)),
    CONSTRAINT `json.payment_transfer.response_data` CHECK (JSON_VALID(`response_data`)),
    CONSTRAINT `json.payment_transfer.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_transfer.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_transfer.channel_config_id` FOREIGN KEY (`channel_config_id`) REFERENCES `payment_channel_config` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_transfer.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_transfer.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_order_transaction (payment flow log, no notifications)
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_order_transaction` (
    `id` BINARY(16) NOT NULL,
    `tenant_id`  BINARY(16)                              NULL,
    `order_id` BINARY(16) NOT NULL COMMENT 'Order ID',
    `transaction_no` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Platform transaction no',
    `type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Payment channel execution type: create / query / close (refunds are stored on payment_refund)',
    `channel_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Channel code (snapshot)',
    `method_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Payment method code (snapshot)',
    `amount` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Operation amount in cents (0 for query operations)',
    `channel_request_no` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Channel request no',
    `channel_trade_no` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Channel transaction no',
    `state_id` BINARY(16) NOT NULL COMMENT 'State ID',
    `request_data` JSON NULL COMMENT 'Provider request snapshot',
    `response_data` JSON NULL COMMENT 'Provider response snapshot',
    `result_code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Result code',
    `result_message` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Result message',
    `operator` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Operator',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_order_transaction.tenant_id` (`tenant_id`),
    UNIQUE `uniq.payment_order_transaction.transaction_no` (`tenant_id`, `transaction_no`),
    UNIQUE `uniq.payment_order_transaction.channel_request` (`tenant_id`, `channel_code`, `channel_request_no`),
    KEY `idx.payment_order_transaction.order_id_created_at` (`order_id`, `created_at`),
    KEY `idx.payment_order_transaction.channel_trade_no` (`channel_code`, `channel_trade_no`),
    KEY `idx.payment_order_transaction.state_created_at` (`state_id`, `created_at`),
    CONSTRAINT `json.payment_order_transaction.request_data` CHECK (JSON_VALID(`request_data`)),
    CONSTRAINT `json.payment_order_transaction.response_data` CHECK (JSON_VALID(`response_data`)),
    CONSTRAINT `json.payment_order_transaction.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_order_transaction.order_id` FOREIGN KEY (`order_id`) REFERENCES `payment_order` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_order_transaction.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_order_transaction.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_order.primary_transaction_id references the later-created transaction table
        if (!$this->foreignKeyExists($connection, 'payment_order', 'fk.payment_order.primary_transaction_id')) {
            $this->executeDdlStatement(
                $connection,
                <<<'SQL'
ALTER TABLE `payment_order`
    ADD CONSTRAINT `fk.payment_order.primary_transaction_id` FOREIGN KEY (`primary_transaction_id`) REFERENCES `payment_order_transaction` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL
            );
        }

        // payment_channel_notify_record (inbound channel notifications)
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_channel_notify_record` (
    `tenant_id`  BINARY(16)                              NULL,
    `id` BINARY(16) NOT NULL,
    `channel_code` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Channel code',
    `channel_config_id` BINARY(16) NOT NULL COMMENT 'Channel configuration used to verify the notification',
    `notification_key` CHAR(64) COLLATE ascii_bin NOT NULL COMMENT 'Stable request fingerprint for idempotency',
    `order_id` BINARY(16) NULL COMMENT 'Order ID (payment and close notifications)',
    `refund_id` BINARY(16) NULL COMMENT 'Refund ID (refund notifications)',
    `transfer_id` BINARY(16) NULL COMMENT 'Transfer ID',
    `recurring_id` BINARY(16) NULL COMMENT 'Recurring agreement ID',
    `notify_type` TINYINT NOT NULL COMMENT 'Notification type: 0=unknown, 1=payment, 2=refund, 3=transfer, 4=subscription',
    `raw_body` LONGTEXT COLLATE utf8mb4_unicode_ci NULL COMMENT 'Raw channel notification payload (as received)',
    `response_body` LONGTEXT COLLATE utf8mb4_unicode_ci NULL COMMENT 'Platform response (ack / error)',
    `response_content_type` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Provider acknowledgement content type',
    `response_status` SMALLINT NULL COMMENT 'Provider acknowledgement HTTP status',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT 'Status: 0=pending, 1=processed, 2=ignored, 3=failed',
    `error_message` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Error message',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_channel_notify_record.tenant_id` (`tenant_id`),
    KEY `idx.payment_channel_notify_record.channel_created` (`channel_code`, `created_at`),
    UNIQUE `uniq.payment_channel_notify_record.config_notification` (`channel_config_id`, `notification_key`),
    KEY `idx.payment_channel_notify_record.order_id` (`order_id`),
    KEY `idx.payment_channel_notify_record.refund_id` (`refund_id`),
    KEY `idx.payment_channel_notify_record.transfer_id` (`transfer_id`),
    KEY `idx.payment_channel_notify_record.recurring_id` (`recurring_id`),
    CONSTRAINT `json.payment_channel_notify_record.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_channel_notify_record.order_id` FOREIGN KEY (`order_id`) REFERENCES `payment_order` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_channel_notify_record.refund_id` FOREIGN KEY (`refund_id`) REFERENCES `payment_refund` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_channel_notify_record.transfer_id` FOREIGN KEY (`transfer_id`) REFERENCES `payment_transfer` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_channel_notify_record.recurring_id` FOREIGN KEY (`recurring_id`) REFERENCES `payment_recurring` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_channel_notify_record.channel_config_id` FOREIGN KEY (`channel_config_id`) REFERENCES `payment_channel_config` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.payment_channel_notify_record.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );

        // payment_notify_record (outbound notifications to external systems)
        $this->executeDdlStatement(
            $connection,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_notify_record` (
    `tenant_id`  BINARY(16)                              NULL,
    `id` BINARY(16) NOT NULL,
    `order_id` BINARY(16) NULL COMMENT 'Order ID (for payment and close notifications; exactly one of order_id/refund_id must be set, enforced in application layer)',
    `refund_id` BINARY(16) NULL COMMENT 'Refund ID (for refund notifications; exactly one of order_id/refund_id must be set, enforced in application layer)',
    `transfer_id` BINARY(16) NULL COMMENT 'Transfer ID',
    `recurring_id` BINARY(16) NULL COMMENT 'Recurring agreement ID',
    `notify_type` TINYINT NOT NULL COMMENT 'Notification type: 1=payment, 2=refund, 3=transfer, 4=subscription',
    `notify_url` VARCHAR(2048) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Notify URL of the external system',
    `request_body` LONGTEXT COLLATE utf8mb4_unicode_ci NULL COMMENT 'Request body sent to the external system',
    `response_body` LONGTEXT COLLATE utf8mb4_unicode_ci NULL COMMENT 'Response body returned by the external system',
    `response_status` SMALLINT NULL COMMENT 'HTTP status returned by the external system',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT 'Status: 0=pending, 1=processing, 2=success, 3=failed',
    `retry_count` INT NOT NULL DEFAULT 0 COMMENT 'Retry count',
    `available_at` DATETIME(3) NULL COMMENT 'Next retry time',
    `custom_fields` JSON NULL COMMENT 'Custom fields',
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.payment_notify_record.tenant_id` (`tenant_id`),
    KEY `idx.payment_notify_record.status_available_at` (`status`, `available_at`),
    KEY `idx.payment_notify_record.order_id` (`order_id`),
    KEY `idx.payment_notify_record.refund_id` (`refund_id`),
    KEY `idx.payment_notify_record.transfer_id` (`transfer_id`),
    KEY `idx.payment_notify_record.recurring_id` (`recurring_id`),
    CONSTRAINT `json.payment_notify_record.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.payment_notify_record.order_id` FOREIGN KEY (`order_id`) REFERENCES `payment_order` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_notify_record.refund_id` FOREIGN KEY (`refund_id`) REFERENCES `payment_refund` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_notify_record.transfer_id` FOREIGN KEY (`transfer_id`) REFERENCES `payment_transfer` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_notify_record.recurring_id` FOREIGN KEY (`recurring_id`) REFERENCES `payment_recurring` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk.payment_notify_record.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
    }
}
