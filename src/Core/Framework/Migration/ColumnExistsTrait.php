<?php declare(strict_types=1);

namespace Contena\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Util\Database\TableHelper;

trait ColumnExistsTrait
{
    /**
     * @param non-empty-string $table
     */
    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        return TableHelper::columnExists($connection, $table, $column);
    }
}
