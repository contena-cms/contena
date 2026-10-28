<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1787205808AddNumberRangeReadAclPrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order.editor' => [
            'number_range:read',
        ],
        'customer.creator' => [
            'number_range:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1787205808;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
