<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberGroup;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Member\Aggregate\MemberGroupTranslation\MemberGroupTranslationCollection;
use Contena\Core\System\Member\MemberCollection;

class MemberGroupEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?string $name = null;

    protected ?MemberGroupTranslationCollection $translations = null;

    protected ?MemberCollection $members = null;

    protected ?ChannelCollection $channels = null;

    protected bool $registrationActive;

    protected ?string $registrationTitle = null;

    protected ?string $registrationIntroduction = null;

    protected ?string $registrationSeoMetaDescription = null;

    protected ?ChannelCollection $registrationChannels = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTranslations(): ?MemberGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MemberGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getChannels(): ?ChannelCollection
    {
        return $this->channels;
    }

    public function getMembers(): ?MemberCollection
    {
        return $this->members;
    }

    public function setMembers(MemberCollection $members): void
    {
        $this->members = $members;
    }

    public function setChannels(ChannelCollection $channels): void
    {
        $this->channels = $channels;
    }

    public function getRegistrationActive(): bool
    {
        return $this->registrationActive;
    }

    public function setRegistrationActive(bool $registrationActive): void
    {
        $this->registrationActive = $registrationActive;
    }

    public function getRegistrationTitle(): ?string
    {
        return $this->registrationTitle;
    }

    public function setRegistrationTitle(string $registrationTitle): void
    {
        $this->registrationTitle = $registrationTitle;
    }

    public function getRegistrationIntroduction(): ?string
    {
        return $this->registrationIntroduction;
    }

    public function setRegistrationIntroduction(string $registrationIntroduction): void
    {
        $this->registrationIntroduction = $registrationIntroduction;
    }

    public function getRegistrationSeoMetaDescription(): ?string
    {
        return $this->registrationSeoMetaDescription;
    }

    public function setRegistrationSeoMetaDescription(string $registrationSeoMetaDescription): void
    {
        $this->registrationSeoMetaDescription = $registrationSeoMetaDescription;
    }

    public function getRegistrationChannels(): ?ChannelCollection
    {
        return $this->registrationChannels;
    }

    public function setRegistrationChannels(ChannelCollection $registrationChannels): void
    {
        $this->registrationChannels = $registrationChannels;
    }
}
