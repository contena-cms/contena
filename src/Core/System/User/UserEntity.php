<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Notification\NotificationCollection;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Position\PositionCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Contena\Core\System\Tenant\TenantEntity;
use Contena\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyCollection;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;

class UserEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?TenantEntity $tenant = null;

    protected ?string $tenantId = null;

    protected string $localeId;

    protected ?string $userCode = null;

    protected ?string $avatarId = null;

    protected string $username;

    /**
     * @internal
     */
    protected string $password;

    protected string $name;

    protected ?string $phoneNumber = null;

    protected ?string $gender = null;

    protected string $email;

    protected bool $active;

    protected bool $admin;

    protected ?\DateTimeInterface $firstLogin = null;

    protected ?\DateTimeInterface $lastLogin = null;

    protected ?AclRoleCollection $aclRoles = null;

    protected ?PositionCollection $positions = null;

    protected ?LocaleEntity $locale = null;

    protected ?MediaEntity $avatarMedia = null;

    protected ?MediaCollection $media = null;

    protected ?UserAccessKeyCollection $accessKeys = null;

    protected ?UserConfigCollection $configs = null;

    protected ?StateMachineHistoryCollection $stateMachineHistoryEntries = null;

    protected ?NotificationCollection $createdNotifications = null;

    protected ?UserRecoveryEntity $recoveryUser = null;

    protected ?\DateTimeInterface $lastUpdatedPasswordAt = null;

    protected string $timeZone;

    public function getTenant(): ?TenantEntity
    {
        return $this->tenant;
    }

    public function setTenant(?TenantEntity $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getUserCode(): ?string
    {
        return $this->userCode;
    }

    public function setUserCode(?string $userCode): void
    {
        $this->userCode = $userCode;
    }

    public function getStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->stateMachineHistoryEntries;
    }

    public function setStateMachineHistoryEntries(StateMachineHistoryCollection $stateMachineHistoryEntries): void
    {
        $this->stateMachineHistoryEntries = $stateMachineHistoryEntries;
    }

    public function getCreatedNotifications(): ?NotificationCollection
    {
        return $this->createdNotifications;
    }

    public function setCreatedNotifications(NotificationCollection $createdNotifications): void
    {
        $this->createdNotifications = $createdNotifications;
    }

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getAvatarId(): ?string
    {
        return $this->avatarId;
    }

    public function setAvatarId(string $avatarId): void
    {
        $this->avatarId = $avatarId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @internal
     */
    public function getPassword(): string
    {
        $this->checkIfPropertyAccessIsAllowed('password');

        return $this->password;
    }

    /**
     * @internal
     */
    public function setPassword(#[\SensitiveParameter] string $password): void
    {
        $this->password = $password;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLocale(): ?LocaleEntity
    {
        return $this->locale;
    }

    public function setLocale(LocaleEntity $locale): void
    {
        $this->locale = $locale;
    }

    public function getAvatarMedia(): ?MediaEntity
    {
        return $this->avatarMedia;
    }

    public function setAvatarMedia(MediaEntity $avatarMedia): void
    {
        $this->avatarMedia = $avatarMedia;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getAccessKeys(): ?UserAccessKeyCollection
    {
        return $this->accessKeys;
    }

    public function setAccessKeys(UserAccessKeyCollection $accessKeys): void
    {
        $this->accessKeys = $accessKeys;
    }

    public function getConfigs(): ?UserConfigCollection
    {
        return $this->configs;
    }

    public function setConfigs(UserConfigCollection $configs): void
    {
        $this->configs = $configs;
    }

    public function getRecoveryUser(): ?UserRecoveryEntity
    {
        return $this->recoveryUser;
    }

    public function setRecoveryUser(UserRecoveryEntity $recoveryUser): void
    {
        $this->recoveryUser = $recoveryUser;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
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

    public function setAdmin(bool $admin): void
    {
        $this->admin = $admin;
    }

    public function getAclRoles(): ?AclRoleCollection
    {
        return $this->aclRoles;
    }

    public function setAclRoles(AclRoleCollection $aclRoles): void
    {
        $this->aclRoles = $aclRoles;
    }

    public function getPositions(): ?PositionCollection
    {
        return $this->positions;
    }

    public function setPositions(PositionCollection $positions): void
    {
        $this->positions = $positions;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): void
    {
        $this->gender = $gender;
    }

    public function getLastUpdatedPasswordAt(): ?\DateTimeInterface
    {
        return $this->lastUpdatedPasswordAt;
    }

    public function setLastUpdatedPasswordAt(\DateTimeInterface $lastUpdatedPasswordAt): void
    {
        $this->lastUpdatedPasswordAt = $lastUpdatedPasswordAt;
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    public function setTimeZone(string $timeZone): void
    {
        $this->timeZone = $timeZone;
    }
}
