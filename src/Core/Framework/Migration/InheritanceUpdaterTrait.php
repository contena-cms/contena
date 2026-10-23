<?php declare(strict_types=1);

namespace Contena\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

trait InheritanceUpdaterTrait
{
    protected function updateInheritance(Connection $connection, string $entity, string $propertyName): void
    {
        $sql = str_replace(
            ['#table#', '#column#'],
            [$entity, $propertyName],
            'ALTER TABLE `#table#` ADD COLUMN `#column#` binary(16) NULL'
        );

        NonStandardFkGuard::executeDdl($connection, $sql);
    }
}
