<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserAccessKey;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserAccessKeyEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserAccessKeyCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(static fn (UserAccessKeyEntity $user) => $user->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (UserAccessKeyEntity $user) => $user->getUserId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'user_access_key_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserAccessKeyEntity::class;
    }
}
