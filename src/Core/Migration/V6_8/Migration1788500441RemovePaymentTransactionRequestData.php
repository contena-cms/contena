<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1788500441RemovePaymentTransactionRequestData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788500441;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'payment_order_transaction', 'request_data')) {
            return;
        }

        $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `payment_order_transaction`
    DROP COLUMN `request_data`
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
