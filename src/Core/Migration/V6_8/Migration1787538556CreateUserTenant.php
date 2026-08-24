<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * Adds tenant memberships for global administration users.
 *
 * The legacy user.tenant_id column is intentionally retained for the
 * expand/contract migration. Existing rows are copied into user_tenant and
 * new runtime code uses the membership table for tenant access decisions.
 *
 * @internal
 */
class Migration1787538556CreateUserTenant extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787538556;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_tenant` (
    `user_id`    BINARY(16) NOT NULL,
    `tenant_id`  BINARY(16) NOT NULL,
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `admin`      TINYINT(1) NOT NULL DEFAULT 0,
    `user_code`  VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`user_id`, `tenant_id`),
    KEY `idx.user_tenant.tenant_id_user_id` (`tenant_id`, `user_id`),
    UNIQUE KEY `uniq.user_tenant.tenant_id_user_code` (`tenant_id`, `user_code`),
    CONSTRAINT `fk.user_tenant.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.user_tenant.tenant_id` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Keep this statement idempotent so an interrupted migration can be retried.
        $connection->executeStatement(<<<'SQL'
INSERT INTO `user_tenant` (`user_id`, `tenant_id`, `active`, `admin`, `user_code`, `created_at`)
SELECT `id`, `tenant_id`, `active`, COALESCE(`admin`, 0), `user_code`, COALESCE(`created_at`, CURRENT_TIMESTAMP(3))
FROM `user`
WHERE `tenant_id` IS NOT NULL
ON DUPLICATE KEY UPDATE
    `user_id` = VALUES(`user_id`)
SQL);

        // Preserve any tenant relations created through custom code before the
        // membership boundary was introduced.
        $connection->executeStatement(<<<'SQL'
INSERT INTO `user_tenant` (`user_id`, `tenant_id`, `active`, `admin`, `created_at`)
SELECT `membership_source`.`user_id`, `membership_source`.`tenant_id`, `user`.`active`, COALESCE(`user`.`admin`, 0), COALESCE(`user`.`created_at`, CURRENT_TIMESTAMP(3))
FROM (
    SELECT `user_id`, `tenant_id` FROM `acl_user_role` WHERE `tenant_id` IS NOT NULL
    UNION
    SELECT `user_id`, `tenant_id` FROM `user_position` WHERE `tenant_id` IS NOT NULL
    UNION
    SELECT `user_id`, `tenant_id` FROM `user_tag` WHERE `tenant_id` IS NOT NULL
    UNION
    SELECT `user_id`, `tenant_id` FROM `user_config` WHERE `tenant_id` IS NOT NULL
) AS `membership_source`
INNER JOIN `user` ON `user`.`id` = `membership_source`.`user_id`
ON DUPLICATE KEY UPDATE
    `user_id` = VALUES(`user_id`)
SQL);

        $connection->executeStatement('UPDATE `user_access_key` SET `tenant_id` = NULL WHERE `tenant_id` IS NOT NULL');
        $connection->executeStatement('UPDATE `user_recovery` SET `tenant_id` = NULL WHERE `tenant_id` IS NOT NULL');

        foreach (['acl_user_role', 'user_position', 'user_tag', 'user_config'] as $table) {
            $index = 'idx.' . $table . '.user_id_tenant_id';
            if (!$this->indexExists($connection, $table, $index)) {
                $this->executeDdlStatement(
                    $connection,
                    \sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`user_id`, `tenant_id`)', $table, $index),
                );
            }
        }

        if (!$this->columnExists($connection, 'user_config', 'platform_key')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `user_config`
    ADD COLUMN `platform_key` VARCHAR(255) COLLATE utf8mb4_unicode_ci
        GENERATED ALWAYS AS (IF(`tenant_id` IS NULL, `key`, NULL)) VIRTUAL
SQL);
        }

        $this->dropIndexIfExists($connection, 'user_config', 'uniq.user_id_key');

        if (!$this->indexExists($connection, 'user_config', 'uniq.user_config.tenant_user_key')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `user_config`
    ADD UNIQUE INDEX `uniq.user_config.tenant_user_key` (`tenant_id`, `user_id`, `key`)
SQL);
        }

        if (!$this->indexExists($connection, 'user_config', 'uniq.user_config.platform_user_key')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `user_config`
    ADD UNIQUE INDEX `uniq.user_config.platform_user_key` (`user_id`, `platform_key`)
SQL);
        }

        foreach (['acl_user_role', 'user_position', 'user_tag', 'user_config'] as $table) {
            $foreignKey = 'fk.' . $table . '.user_tenant';
            if (!$this->foreignKeyExists($connection, $table, $foreignKey)) {
                $this->executeDdlStatement(
                    $connection,
                    \sprintf(
                        'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `user_tenant` (`user_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE',
                        $table,
                        $foreignKey,
                    ),
                );
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
