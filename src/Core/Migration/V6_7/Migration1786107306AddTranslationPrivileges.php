<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1786107306AddTranslationPrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'language.viewer' => [
            'sales_channel:read',
            'system:translation:read',
        ],
        'language.editor' => [
            'system:translation:create',
        ],
        'language.deleter' => [
            'system:translation:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1786107306;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
