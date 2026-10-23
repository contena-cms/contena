<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AdminUserRecoveryEntity>
 *
 * @codeCoverageIgnore
 */
class AdminUserRecoveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'admin_user_recovery_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminUserRecoveryEntity::class;
    }
}
