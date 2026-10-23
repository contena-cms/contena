<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AdminUserAccessKeyEntity>
 *
 * @codeCoverageIgnore
 */
class AdminUserAccessKeyCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(static fn (AdminUserAccessKeyEntity $admin_user) => $admin_user->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (AdminUserAccessKeyEntity $admin_user) => $admin_user->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'admin_user_access_key_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminUserAccessKeyEntity::class;
    }
}
