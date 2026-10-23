<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserGroupTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserGroupTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class UserGroupTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getUserGroupIds(): array
    {
        return $this->fmap(static fn (UserGroupTranslationEntity $userGroupTranslation) => $userGroupTranslation->getUserGroupId());
    }

    public function filterByUserGroupId(string $id): self
    {
        return $this->filter(static fn (UserGroupTranslationEntity $userGroupTranslation) => $userGroupTranslation->getUserGroupId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (UserGroupTranslationEntity $userGroupTranslation) => $userGroupTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (UserGroupTranslationEntity $userGroupTranslation) => $userGroupTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'user_group_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserGroupTranslationEntity::class;
    }
}
