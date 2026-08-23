<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberGroupTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MemberGroupTranslationEntity>
 */
class MemberGroupTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getMemberGroupIds(): array
    {
        return $this->fmap(static fn (MemberGroupTranslationEntity $memberGroupTranslation) => $memberGroupTranslation->getMemberGroupId());
    }

    public function filterByMemberGroupId(string $id): self
    {
        return $this->filter(static fn (MemberGroupTranslationEntity $memberGroupTranslation) => $memberGroupTranslation->getMemberGroupId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (MemberGroupTranslationEntity $memberGroupTranslation) => $memberGroupTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (MemberGroupTranslationEntity $memberGroupTranslation) => $memberGroupTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'member_group_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MemberGroupTranslationEntity::class;
    }
}
