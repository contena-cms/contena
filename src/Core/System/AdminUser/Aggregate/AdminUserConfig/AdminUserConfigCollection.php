<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserConfig;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AdminUserConfigEntity>
 *
 * @codeCoverageIgnore
 */
class AdminUserConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'admin_user_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminUserConfigEntity::class;
    }
}
