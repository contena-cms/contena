<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1784207056CreateMailHeaderFooter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207056;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_header_footer` (
    `id`             BINARY(16)          NOT NULL,
    `data_scope_id`      BINARY(16)          NOT NULL,
    `system_default` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `created_at`     DATETIME(3)         NOT NULL,
    `updated_at`     DATETIME(3)         NULL,
    PRIMARY KEY (`id`),
    KEY `idx.mail_header_footer.data_scope_id` (`data_scope_id`),
    CONSTRAINT `fk.mail_header_footer.data_scope_id`
        FOREIGN KEY (`data_scope_id`) REFERENCES `data_scope` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
