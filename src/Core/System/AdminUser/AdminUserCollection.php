<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AdminUserEntity>
 *
 * @codeCoverageIgnore
 */
class AdminUserCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getLocaleIds(): array
    {
        return $this->fmap(static fn (AdminUserEntity $admin_user) => $admin_user->getLocaleId());
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(static fn (AdminUserEntity $admin_user) => $admin_user->getLocaleId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'admin_user_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminUserEntity::class;
    }
}
