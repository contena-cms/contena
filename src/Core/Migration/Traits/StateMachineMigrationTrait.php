<?php declare(strict_types=1);

namespace Contena\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
trait StateMachineMigrationTrait
{
    private function import(StateMachineMigration $migration, Connection $connection): StateMachineMigration
    {
        return (new StateMachineMigrationImporter($connection))->importStateMachine($migration);
    }
}
