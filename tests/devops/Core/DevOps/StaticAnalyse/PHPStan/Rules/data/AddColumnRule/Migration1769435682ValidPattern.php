<?php

declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\AddColumnRule;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

class Migration1769435682ValidPattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769435682;
    }

    public function update(Connection $connection): void
    {
        // This is valid - using addColumn helper
        $this->addColumn($connection, 'product', 'states', 'JSON', true, 'NULL');
    }
}
