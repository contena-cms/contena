<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_8;

use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Util\Database\TableHelper;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class Migration1788425391AddPaymentNotifyResponseStatus extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788425391;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'payment_notify_record', 'response_status')) {
            return;
        }

        $this->executeDdlStatement($connection, <<<'SQL'
ALTER TABLE `payment_notify_record`
    ADD COLUMN `response_status` SMALLINT NULL COMMENT 'HTTP status returned by the external system' AFTER `response_body`
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
