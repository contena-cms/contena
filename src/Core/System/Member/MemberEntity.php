<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\User\UserEntity;

class MemberEntity extends Entity implements \Stringable
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $groupId;

    protected string $channelId;

    protected string $languageId;

    protected string $memberNumber;

    protected string $name;

    protected ?string $phoneNumber = null;

    /**
     * @internal
     */
    protected ?string $password = null;

    protected string $email;

    protected ?string $title = null;

    protected bool $active;

    protected bool $doubleOptInRegistration;

    protected ?\DateTimeInterface $doubleOptInEmailSentDate = null;

    protected ?\DateTimeInterface $doubleOptInConfirmDate = null;

    protected ?string $hash = null;

    protected ?\DateTimeInterface $firstLogin = null;

    protected ?\DateTimeInterface $lastLogin = null;

    protected ?\DateTimeInterface $birthday = null;

    protected ?MemberGroupEntity $group = null;

    protected ?ChannelEntity $channel = null;

    protected ?LanguageEntity $language = null;

    protected ?MemberAddressCollection $addresses = null;

    protected int $autoIncrement;

    protected ?TagCollection $tags = null;

    /**
     * @var list<string>|null
     */
    protected ?array $tagIds = null;

    protected ?MemberRecoveryEntity $recoveryMember = null;

    protected ?string $remoteAddress = null;

    protected ?string $requestedGroupId = null;

    protected ?MemberGroupEntity $requestedGroup = null;

    protected ?string $createdById = null;

    protected ?UserEntity $createdBy = null;

    protected ?string $updatedById = null;

    protected ?UserEntity $updatedBy = null;

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getMemberNumber(): string
    {
        return $this->memberNumber;
    }

    public function setMemberNumber(string $memberNumber): void
    {
        $this->memberNumber = $memberNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * @internal
     */
    public function getPassword(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('password');

        return $this->password;
    }

    /**
     * @internal
     */
    public function setPassword(#[\SensitiveParameter] ?string $password): void
    {
        $this->password = $password;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getDoubleOptInRegistration(): bool
    {
        return $this->doubleOptInRegistration;
    }

    public function setDoubleOptInRegistration(bool $doubleOptInRegistration): void
    {
        $this->doubleOptInRegistration = $doubleOptInRegistration;
    }

    public function getDoubleOptInEmailSentDate(): ?\DateTimeInterface
    {
        return $this->doubleOptInEmailSentDate;
    }

    public function setDoubleOptInEmailSentDate(\DateTimeInterface $doubleOptInEmailSentDate): void
    {
        $this->doubleOptInEmailSentDate = $doubleOptInEmailSentDate;
    }

    public function getDoubleOptInConfirmDate(): ?\DateTimeInterface
    {
        return $this->doubleOptInConfirmDate;
    }

    public function setDoubleOptInConfirmDate(\DateTimeInterface $doubleOptInConfirmDate): void
    {
        $this->doubleOptInConfirmDate = $doubleOptInConfirmDate;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getFirstLogin(): ?\DateTimeInterface
    {
        return $this->firstLogin;
    }

    public function setFirstLogin(?\DateTimeInterface $firstLogin): void
    {
        $this->firstLogin = $firstLogin;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getGroup(): ?MemberGroupEntity
    {
        return $this->group;
    }

    public function setGroup(MemberGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getAddresses(): ?MemberAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(MemberAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return list<string>|null
     */
    public function getTagIds(): ?array
    {
        return $this->tagIds;
    }

    /**
     * @param list<string> $tagIds
     */
    public function setTagIds(array $tagIds): void
    {
        $this->tagIds = $tagIds;
    }

    public function getRecoveryMember(): ?MemberRecoveryEntity
    {
        return $this->recoveryMember;
    }

    public function setRecoveryMember(?MemberRecoveryEntity $recoveryMember): void
    {
        $this->recoveryMember = $recoveryMember;
    }

    public function getRemoteAddress(): ?string
    {
        return $this->remoteAddress;
    }

    public function setRemoteAddress(?string $remoteAddress): void
    {
        $this->remoteAddress = $remoteAddress;
    }

    public function getRequestedGroupId(): ?string
    {
        return $this->requestedGroupId;
    }

    public function setRequestedGroupId(?string $requestedGroupId): void
    {
        $this->requestedGroupId = $requestedGroupId;
    }

    public function getRequestedGroup(): ?MemberGroupEntity
    {
        return $this->requestedGroup;
    }

    public function setRequestedGroup(?MemberGroupEntity $requestedGroup): void
    {
        $this->requestedGroup = $requestedGroup;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(string $createdById): void
    {
        $this->createdById = $createdById;
    }

    public function getCreatedBy(): ?UserEntity
    {
        return $this->createdBy;
    }

    public function setCreatedBy(UserEntity $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedById(): ?string
    {
        return $this->updatedById;
    }

    public function setUpdatedById(string $updatedById): void
    {
        $this->updatedById = $updatedById;
    }

    public function getUpdatedBy(): ?UserEntity
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(UserEntity $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }
}
