<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Defaults;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;

/**
 * Creates the canonical, non-null ownership boundary for business data.
 *
 * @internal
 */
class Migration1784205000CreateDataScope extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784205000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(\sprintf(<<<'SQL'
CREATE TABLE IF NOT EXISTS `data_scope` (
    `id`   BINARY(16)                         NOT NULL,
    `type` ENUM('platform', 'tenant')         NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `chk.data_scope.canonical_identity` CHECK (
        (`type` = 'platform' AND `id` = UNHEX('%1$s'))
        OR (`type` = 'tenant' AND `id` <> UNHEX('%1$s'))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, Defaults::PLATFORM_DATA_SCOPE));

        $connection->executeStatement(
            'INSERT IGNORE INTO `data_scope` (`id`, `type`) VALUES (:id, :type)',
            [
                'id' => Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE),
                'type' => 'platform',
            ],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
