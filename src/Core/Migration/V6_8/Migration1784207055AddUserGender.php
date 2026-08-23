<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1784207055AddUserGender extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784207055;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'user',
            'gender',
            'VARCHAR(255) COLLATE utf8mb4_unicode_ci'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
