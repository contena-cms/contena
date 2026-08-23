<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207021CreateLanguage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207021;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `language` (
    `id`                  BINARY(16)                             NOT NULL,
    `name`                VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `parent_id`           BINARY(16)                             NULL,
    `locale_id`           BINARY(16)                             NOT NULL,
    `translation_code_id` BINARY(16) DEFAULT NULL,
    `active`              TINYINT(1)                             NOT NULL DEFAULT 0,
    `translation_auto_update` TINYINT(1)                         NOT NULL DEFAULT 1,
    `custom_fields`       JSON                                   NULL,
    `created_at`          DATETIME(3)                            NOT NULL,
    `updated_at`          DATETIME(3)                            NULL,
    PRIMARY KEY (`id`),
    KEY `idx.language.translation_code_id` (`translation_code_id`),
    KEY `idx.language.language_id_parent_language_id` (`id`, `parent_id`),
    CONSTRAINT `json.language.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.language.parent_id` FOREIGN KEY (`parent_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.language.locale_id` FOREIGN KEY (`locale_id`)
        REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.language.translation_code_id` FOREIGN KEY (`translation_code_id`)
        REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `snippet_set` (
    `id`            BINARY(16)                              NOT NULL,
    `name`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `base_file`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `iso`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `custom_fields` JSON                                    NULL,
    `created_at`    DATETIME(3)                             NOT NULL,
    `updated_at`    DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `json.snippet_set.custom_fields` CHECK (JSON_VALID(`custom_fields`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `snippet` (
    `id`              BINARY(16)                              NOT NULL,
    `translation_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `value`           LONGTEXT COLLATE utf8mb4_unicode_ci     NOT NULL,
    `author`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `snippet_set_id`  BINARY(16)                              NOT NULL,
    `custom_fields`   JSON                                    NULL,
    `created_at`      DATETIME(3)                             NOT NULL,
    `updated_at`      DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.snippet_set_id_translation_key` (`snippet_set_id`, `translation_key`),
    CONSTRAINT `json.snippet.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.snippet.snippet_set_id` FOREIGN KEY (`snippet_set_id`)
        REFERENCES `snippet_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
