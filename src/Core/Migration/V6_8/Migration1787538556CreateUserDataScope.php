<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * Adds explicit data-scope grants for global administration identities.
 *
 * @internal
 */
class Migration1787538556CreateUserDataScope extends MigrationStep
{
    private const array SCOPED_USER_RELATIONS = [
        'acl_user_role',
        'user_position',
        'user_tag',
        'user_config',
    ];

    public function getCreationTimestamp(): int
    {
        return 1787538556;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `user_data_scope` (
    `user_id`         BINARY(16)                              NOT NULL,
    `data_scope_id`   BINARY(16)                              NOT NULL,
    `active`           TINYINT(1)                              NOT NULL DEFAULT 1,
    `admin`            TINYINT(1)                              NOT NULL DEFAULT 0,
    `read_all_scopes`  TINYINT(1)                              NOT NULL DEFAULT 0,
    `user_code`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `created_at`       DATETIME(3)                             NOT NULL,
    `updated_at`       DATETIME(3)                             NULL,
    PRIMARY KEY (`user_id`, `data_scope_id`),
    KEY `idx.user_data_scope.data_scope_id_user_id` (`data_scope_id`, `user_id`),
    UNIQUE KEY `uniq.user_data_scope.data_scope_id_user_code` (`data_scope_id`, `user_code`),
    CONSTRAINT `fk.user_data_scope.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.user_data_scope.data_scope_id` FOREIGN KEY (`data_scope_id`)
        REFERENCES `data_scope` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        foreach (self::SCOPED_USER_RELATIONS as $table) {
            $index = 'idx.' . $table . '.user_id_data_scope_id';
            if (!$this->indexExists($connection, $table, $index)) {
                $this->executeDdlStatement(
                    $connection,
                    \sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`user_id`, `data_scope_id`)', $table, $index),
                );
            }
        }

        $this->dropIndexIfExists($connection, 'user_config', 'uniq.user_id_key');
        if (!$this->indexExists($connection, 'user_config', 'uniq.user_config.data_scope_user_key')) {
            $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `user_config`
    ADD UNIQUE INDEX `uniq.user_config.data_scope_user_key` (`data_scope_id`, `user_id`, `key`)
SQL);
        }

        foreach (self::SCOPED_USER_RELATIONS as $table) {
            $foreignKey = 'fk.' . $table . '.user_data_scope';
            if (!$this->foreignKeyExists($connection, $table, $foreignKey)) {
                $this->executeDdlStatement(
                    $connection,
                    \sprintf(
                        'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`user_id`, `data_scope_id`) REFERENCES `user_data_scope` (`user_id`, `data_scope_id`) ON DELETE CASCADE ON UPDATE CASCADE',
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
