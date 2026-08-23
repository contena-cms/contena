<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;

/**
 * @extends EntityCollection<MemberEntity>
 */
class MemberCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getGroupIds(): array
    {
        return $this->fmap(static fn (MemberEntity $member) => $member->getGroupId());
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(static fn (MemberEntity $member) => $member->getGroupId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getChannelIds(): array
    {
        return $this->fmap(static fn (MemberEntity $member) => $member->getChannelId());
    }

    public function filterByChannelId(string $id): self
    {
        return $this->filter(static fn (MemberEntity $member) => $member->getChannelId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (MemberEntity $member) => $member->getLanguageId());
    }

    public function getGroups(): MemberGroupCollection
    {
        return new MemberGroupCollection(
            $this->fmap(static fn (MemberEntity $member) => $member->getGroup())
        );
    }

    public function getChannels(): ChannelCollection
    {
        return new ChannelCollection(
            $this->fmap(static fn (MemberEntity $member) => $member->getChannel())
        );
    }

    public function getApiAlias(): string
    {
        return 'member_collection';
    }

    protected function getExpectedClass(): string
    {
        return MemberEntity::class;
    }
}
