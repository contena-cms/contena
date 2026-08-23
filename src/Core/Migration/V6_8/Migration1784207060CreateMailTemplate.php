<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207060CreateMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207060;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template` (
    `id`                   BINARY(16)          NOT NULL,
    `tenant_id`            BINARY(16)          NULL,
    `mail_template_type_id` BINARY(16)         NULL,
    `system_default`       TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `was_modified_by_user` TINYINT(1)          NOT NULL DEFAULT 0,
    `created_at`           DATETIME(3)         NOT NULL,
    `updated_at`           DATETIME(3)         NULL,
    PRIMARY KEY (`id`),
    KEY `idx.mail_template.tenant_id` (`tenant_id`),
    CONSTRAINT `fk.mail_template.mail_template_type_id`
        FOREIGN KEY (`mail_template_type_id`) REFERENCES `mail_template_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.mail_template.tenant_id`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
