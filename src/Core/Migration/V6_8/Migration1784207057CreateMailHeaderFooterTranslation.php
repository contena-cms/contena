<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207057CreateMailHeaderFooterTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207057;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_header_footer_translation` (
    `mail_header_footer_id` BINARY(16)   NOT NULL,
    `language_id`           BINARY(16)   NOT NULL,
    `tenant_id`             BINARY(16)   NULL,
    `name`                  VARCHAR(255) NULL,
    `description`           LONGTEXT     NULL,
    `header_html`           LONGTEXT     NULL,
    `header_plain`          LONGTEXT     NULL,
    `footer_html`           LONGTEXT     NULL,
    `footer_plain`          LONGTEXT     NULL,
    `created_at`            DATETIME(3)  NOT NULL,
    `updated_at`            DATETIME(3)  NULL,
    PRIMARY KEY (`mail_header_footer_id`, `language_id`),
    KEY `idx.mail_header_footer_translation.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.mail_header_footer_translation.mail_header_footer_id`
        FOREIGN KEY (`mail_header_footer_id`) REFERENCES `mail_header_footer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_header_footer_translation.language_id`
        FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_header_footer_translation.tenant_id`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
