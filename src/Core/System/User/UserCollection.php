<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserEntity>
 */
class UserCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getLocaleIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getLocaleId());
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(static fn (UserEntity $user) => $user->getLocaleId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'user_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserEntity::class;
    }
}
