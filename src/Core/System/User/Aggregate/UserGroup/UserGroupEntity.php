<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserGroup;

use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @codeCoverageIgnore
 */
class UserGroupEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected bool $displayGross;

    protected ?UserGroupTranslationCollection $translations = null;

    protected ?UserCollection $users = null;

    protected ?ChannelCollection $channels = null;

    protected bool $registrationActive;

    protected ?string $registrationTitle = null;

    protected ?string $registrationIntroduction = null;

    protected ?bool $registrationOnlyCompanyRegistration = null;

    protected ?string $registrationSeoMetaDescription = null;

    protected ?ChannelCollection $registrationChannels = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDisplayGross(): bool
    {
        return $this->displayGross;
    }

    public function setDisplayGross(bool $displayGross): void
    {
        $this->displayGross = $displayGross;
    }

    public function getTranslations(): ?UserGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(UserGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }

    public function getChannels(): ?ChannelCollection
    {
        return $this->channels;
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

    public function getRegistrationOnlyCompanyRegistration(): ?bool
    {
        return $this->registrationOnlyCompanyRegistration;
    }

    public function setRegistrationOnlyCompanyRegistration(bool $registrationOnlyCompanyRegistration): void
    {
        $this->registrationOnlyCompanyRegistration = $registrationOnlyCompanyRegistration;
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
