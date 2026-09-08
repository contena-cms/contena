<?php

declare(strict_types=1);

namespace Contena\Core\Migration\V6_5;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

class Migration1720610754FooBar extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720610754;
    }

    public function update(Connection $connection): void
    {
        // older migrations should not be considered
        $this->dropColumnIfExists($connection, 'test_table', 'column');
    }
}
