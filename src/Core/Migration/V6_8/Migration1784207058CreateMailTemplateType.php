<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207058CreateMailTemplateType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207058;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `mail_template_type` (
    `id`                 BINARY(16)                              NOT NULL,
    `technical_name`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `available_entities` JSON                                    NULL,
    `created_at`         DATETIME(3)                             NOT NULL,
    `updated_at`         DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.mail_template_type.technical_name` (`technical_name`),
    CONSTRAINT `json.mail_template_type.available_entities` CHECK (JSON_VALID(`available_entities`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
