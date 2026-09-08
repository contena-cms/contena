<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle;

use Contena\Core\Defaults;
use Contena\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Contena\Core\Framework\App\Privileges\Privileges;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * @internal only for use by the app-system
 */
class PermissionLifecycleService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Privileges $privileges,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @internal only for use by the app-system
     */
    public function updatePrivileges(?Permissions $permissions, string $appId, bool $acceptPermissions, Context $context): void
    {
        $privileges = $permissions ? $permissions->asParsedPrivileges() : [];

        if ($acceptPermissions) {
            $this->privileges->setPrivileges($appId, $privileges, $context);

            return;
        }

        $this->privileges->requestPrivileges($appId, $privileges, $context);
    }

    /**
     * @internal only for use by the app-system
     */
    public function removeRole(string $roleId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `acl_role` WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }

    public function softDeleteRole(string $roleId): void
    {
        $this->connection->executeStatement(
            'UPDATE `acl_role` SET `deleted_at` = :datetime WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
                'datetime' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
