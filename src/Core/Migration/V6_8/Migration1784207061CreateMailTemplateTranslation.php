<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207061CreateMailTemplateTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207061;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template_translation` (
    `mail_template_id` BINARY(16)   NOT NULL,
    `language_id`      BINARY(16)   NOT NULL,
    `tenant_id`        BINARY(16)   NULL,
    `sender_name`      VARCHAR(255) NULL,
    `subject`          VARCHAR(255) NULL,
    `description`      LONGTEXT     NULL,
    `content_html`     LONGTEXT     NULL,
    `content_plain`    LONGTEXT     NULL,
    `custom_fields`    JSON         NULL,
    `created_at`       DATETIME(3)  NOT NULL,
    `updated_at`       DATETIME(3)  NULL,
    PRIMARY KEY (`mail_template_id`, `language_id`),
    KEY `idx.mail_template_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `json.mail_template_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
    CONSTRAINT `fk.mail_template_translation.mail_template_id`
        FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_template_translation.language_id`
        FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_template_translation.tenant_id`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
