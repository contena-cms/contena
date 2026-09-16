<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Defaults;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\Traits\ImportTranslationsTrait;
use Contena\Core\Migration\Traits\Translations;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1789355612CreateCurrency extends MigrationStep
{
    use ImportTranslationsTrait;

    /**
     * @var list<array{id: string, isoCode: string, symbol: string, position: int, precision: int, zh-CN: string, en-GB: string}>
     */
    private const array CURRENCIES = [
        ['id' => Defaults::CURRENCY, 'isoCode' => Defaults::DEFAULT_CURRENCY_CODE, 'symbol' => '¥', 'position' => 1, 'precision' => 2, 'zh-CN' => '人民币', 'en-GB' => 'Renminbi'],
        ['id' => '82b48d63e05150c3206a2bb07d71e2df', 'isoCode' => 'USD', 'symbol' => '$', 'position' => 2, 'precision' => 2, 'zh-CN' => '美元', 'en-GB' => 'US Dollar'],
        ['id' => 'eab9b36abc4faf44b461b4d9e451913e', 'isoCode' => 'EUR', 'symbol' => '€', 'position' => 3, 'precision' => 2, 'zh-CN' => '欧元', 'en-GB' => 'Euro'],
        ['id' => 'd994894efa00d14864198b29870e78c1', 'isoCode' => 'GBP', 'symbol' => '£', 'position' => 4, 'precision' => 2, 'zh-CN' => '英镑', 'en-GB' => 'Pound Sterling'],
        ['id' => '6be9e45be568c8c1edfe60f28692774f', 'isoCode' => 'JPY', 'symbol' => '¥', 'position' => 5, 'precision' => 0, 'zh-CN' => '日元', 'en-GB' => 'Japanese Yen'],
        ['id' => '22252938b34d6edf9981303d79596298', 'isoCode' => 'HKD', 'symbol' => 'HK$', 'position' => 6, 'precision' => 2, 'zh-CN' => '港元', 'en-GB' => 'Hong Kong Dollar'],
        ['id' => '49d7b1e18b07ecce989a962bdcd853ef', 'isoCode' => 'SGD', 'symbol' => 'S$', 'position' => 7, 'precision' => 2, 'zh-CN' => '新加坡元', 'en-GB' => 'Singapore Dollar'],
        ['id' => 'ddefb87e5c1373fa1fb07e864762367f', 'isoCode' => 'AUD', 'symbol' => 'A$', 'position' => 8, 'precision' => 2, 'zh-CN' => '澳大利亚元', 'en-GB' => 'Australian Dollar'],
        ['id' => '4aca87200df94a761c15d5ab834cad3a', 'isoCode' => 'CAD', 'symbol' => 'C$', 'position' => 9, 'precision' => 2, 'zh-CN' => '加拿大元', 'en-GB' => 'Canadian Dollar'],
        ['id' => 'fb4365b58f5cac1b8a033b759de392c5', 'isoCode' => 'CHF', 'symbol' => 'CHF', 'position' => 10, 'precision' => 2, 'zh-CN' => '瑞士法郎', 'en-GB' => 'Swiss Franc'],
    ];

    public function getCreationTimestamp(): int
    {
        return 1789355612;
    }

    public function update(Connection $connection): void
    {
        $this->createTables($connection);
        $this->createCurrencies($connection);
        $this->configureChannels($connection);
        $this->addDefaultAdministratorPrivileges($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createTables(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `currency` (
    `id`                BINARY(16)                              NOT NULL,
    `factor`            DOUBLE                                  NOT NULL DEFAULT 1,
    `symbol`            VARCHAR(16) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `iso_code`          VARCHAR(3) COLLATE utf8mb4_unicode_ci   NOT NULL,
    `position`          INT                                     NOT NULL DEFAULT 1,
    `decimal_precision` INT                                     NOT NULL DEFAULT 2,
    `created_at`        DATETIME(3)                             NOT NULL,
    `updated_at`        DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.currency.iso_code` (`iso_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `currency_translation` (
    `currency_id` BINARY(16)                              NOT NULL,
    `language_id` BINARY(16)                              NOT NULL,
    `short_name`  VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `name`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `custom_fields` JSON                                  NULL,
    `created_at`  DATETIME(3)                             NOT NULL,
    `updated_at`  DATETIME(3)                             NULL,
    PRIMARY KEY (`currency_id`, `language_id`),
    KEY `idx.currency_translation.language_id` (`language_id`),
    CONSTRAINT `json.currency_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.currency_translation.currency_id` FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.currency_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function createCurrencies(Connection $connection): void
    {
        $createdAt = new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach (self::CURRENCIES as $currency) {
            $connection->executeStatement(
                'INSERT IGNORE INTO `currency` (`id`, `factor`, `symbol`, `iso_code`, `position`, `decimal_precision`, `created_at`)
                 VALUES (:id, 1, :symbol, :isoCode, :position, :precision, :createdAt)',
                [
                    'id' => Uuid::fromHexToBytes($currency['id']),
                    'symbol' => $currency['symbol'],
                    'isoCode' => $currency['isoCode'],
                    'position' => $currency['position'],
                    'precision' => $currency['precision'],
                    'createdAt' => $createdAt,
                ]
            );

            $currencyId = $connection->fetchOne('SELECT `id` FROM `currency` WHERE `iso_code` = :isoCode', ['isoCode' => $currency['isoCode']]);
            if (!\is_string($currencyId)) {
                continue;
            }

            $this->importTranslation('currency_translation', new Translations(
                ['currency_id' => $currencyId, 'short_name' => $currency['isoCode'], 'name' => $currency['zh-CN']],
                ['currency_id' => $currencyId, 'short_name' => $currency['isoCode'], 'name' => $currency['en-GB']],
            ), $connection);
        }
    }

    private function configureChannels(Connection $connection): void
    {
        $this->addColumn($connection, 'channel', 'currency_id', 'BINARY(16)');
        $connection->executeStatement(
            'UPDATE `channel` SET `currency_id` = :currencyId WHERE `currency_id` IS NULL',
            ['currencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)],
        );
        $this->executeDdlStatement($connection, 'ALTER TABLE `channel` MODIFY `currency_id` BINARY(16) NOT NULL');
        if (!$this->foreignKeyExists($connection, 'channel', 'fk.channel.currency_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `channel`
    ADD CONSTRAINT `fk.channel.currency_id` FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL);
        }

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `channel_currency` (
    `data_scope_id` BINARY(16) NOT NULL,
    `channel_id`    BINARY(16) NOT NULL,
    `currency_id`   BINARY(16) NOT NULL,
    PRIMARY KEY (`channel_id`, `currency_id`),
    KEY `idx.channel_currency.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.channel_currency.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_currency.channel_id` FOREIGN KEY (`channel_id`)
        REFERENCES `channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.channel_currency.currency_id` FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
INSERT IGNORE INTO `channel_currency` (`data_scope_id`, `channel_id`, `currency_id`)
SELECT `data_scope_id`, `id`, `currency_id`
FROM `channel`
SQL);

        $this->addColumn($connection, 'channel_domain', 'currency_id', 'BINARY(16)');
        $connection->executeStatement(
            'UPDATE `channel_domain` domain
             INNER JOIN `channel` ON `channel`.`id` = domain.`channel_id`
             SET domain.`currency_id` = channel.`currency_id`
             WHERE domain.`currency_id` IS NULL',
        );
        $this->executeDdlStatement($connection, 'ALTER TABLE `channel_domain` MODIFY `currency_id` BINARY(16) NOT NULL');
        if (!$this->foreignKeyExists($connection, 'channel_domain', 'fk.channel_domain.currency_id')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `channel_domain`
    ADD CONSTRAINT `fk.channel_domain.currency_id` FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL);
        }
    }

    private function addDefaultAdministratorPrivileges(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'acl_role')) {
            return;
        }

        $encoded = $connection->fetchOne('SELECT `privileges` FROM `acl_role` WHERE `code` = :code', ['code' => 'administrator']);
        if (!\is_string($encoded)) {
            return;
        }

        $privileges = Json::decodeToArray($encoded);
        foreach (['currency', 'currency_translation', 'channel_currency'] as $resource) {
            foreach (['read', 'create', 'update', 'delete'] as $operation) {
                $privileges[] = $resource . ':' . $operation;
            }
        }
        sort($privileges);

        $connection->update(
            'acl_role',
            ['privileges' => Json::encode(array_values(array_unique($privileges)))],
            ['code' => 'administrator'],
        );
    }
}
