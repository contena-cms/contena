<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1788665786RemovePaymentTransactionPlaceholders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788665786;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'payment_order_transaction', 'type')
            || !TableHelper::getColumnOfTable($connection, 'payment_order_transaction', 'type')->isNotNull
        ) {
            return;
        }

        $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `payment_order_transaction`
    MODIFY COLUMN `type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Deprecated payment operation'
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        foreach (['type', 'operator'] as $column) {
            if (!TableHelper::columnExists($connection, 'payment_order_transaction', $column)) {
                continue;
            }

            $this->executeDdlStatement($connection, \sprintf(
                'ALTER TABLE `payment_order_transaction` DROP COLUMN `%s`',
                $column,
            ));
        }
    }
}
