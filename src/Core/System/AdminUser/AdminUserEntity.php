<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser;

use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Webhook\WebhookCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey\AdminUserAccessKeyCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Contena\Core\System\User\UserCollection;

class AdminUserEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $localeId;

    protected ?string $avatarId = null;

    protected string $username;

    /**
     * @internal
     */
    protected string $password;

    protected string $name;

    protected ?string $phone = null;

    protected ?string $title = null;

    protected string $email;

    protected bool $active;

    protected bool $admin;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $mcpAllowlist = null;

    protected ?AclRoleCollection $aclRoles = null;

    protected ?LocaleEntity $locale = null;

    protected ?MediaEntity $avatarMedia = null;

    protected ?MediaCollection $media = null;

    protected ?AdminUserAccessKeyCollection $accessKeys = null;

    protected ?AdminUserConfigCollection $configs = null;

    protected ?WebhookCollection $webhooks = null;

    protected ?StateMachineHistoryCollection $stateMachineHistoryEntries = null;

    protected ?ImportExportLogCollection $importExportLogEntries = null;

    protected ?AdminUserRecoveryEntity $recoveryUser = null;

    /**
     * @internal
     */
    protected ?string $storeToken = null;

    protected ?\DateTimeInterface $lastUpdatedPasswordAt = null;



    protected ?UserCollection $createdUsers = null;

    protected ?UserCollection $updatedUsers = null;

    protected string $timeZone;

    public function getStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->stateMachineHistoryEntries;
    }

    public function setStateMachineHistoryEntries(StateMachineHistoryCollection $stateMachineHistoryEntries): void
    {
        $this->stateMachineHistoryEntries = $stateMachineHistoryEntries;
    }

    public function getImportExportLogEntries(): ?ImportExportLogCollection
    {
        return $this->importExportLogEntries;
    }

    public function setImportExportLogEntries(ImportExportLogCollection $importExportLogEntries): void
    {
        $this->importExportLogEntries = $importExportLogEntries;
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
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

    public function getAccessKeys(): ?AdminUserAccessKeyCollection
    {
        return $this->accessKeys;
    }

    public function setAccessKeys(AdminUserAccessKeyCollection $accessKeys): void
    {
        $this->accessKeys = $accessKeys;
    }

    public function getConfigs(): ?AdminUserConfigCollection
    {
        return $this->configs;
    }

    public function setConfigs(AdminUserConfigCollection $configs): void
    {
        $this->configs = $configs;
    }

    public function getRecoveryUser(): ?AdminUserRecoveryEntity
    {
        return $this->recoveryUser;
    }

    public function setRecoveryUser(AdminUserRecoveryEntity $recoveryUser): void
    {
        $this->recoveryUser = $recoveryUser;
    }

    /**
     * @internal
     */
    public function getStoreToken(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('storeToken');

        return $this->storeToken;
    }

    /**
     * @internal
     */
    public function setStoreToken(#[\SensitiveParameter] ?string $storeToken): void
    {
        $this->storeToken = $storeToken;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): void
    {
        $this->admin = $admin;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMcpAllowlist(): ?array
    {
        return $this->mcpAllowlist;
    }

    /**
     * @param array<string, mixed>|null $mcpAllowlist
     */
    public function setMcpAllowlist(?array $mcpAllowlist): void
    {
        $this->mcpAllowlist = $mcpAllowlist;
    }

    public function getAclRoles(): ?AclRoleCollection
    {
        return $this->aclRoles;
    }

    public function setAclRoles(AclRoleCollection $aclRoles): void
    {
        $this->aclRoles = $aclRoles;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getCreatedUsers(): ?UserCollection
    {
        return $this->createdUsers;
    }

    public function setCreatedUsers(UserCollection $createdUsers): void
    {
        $this->createdUsers = $createdUsers;
    }

    public function getUpdatedUsers(): ?UserCollection
    {
        return $this->updatedUsers;
    }

    public function setUpdatedUsers(UserCollection $updatedUsers): void
    {
        $this->updatedUsers = $updatedUsers;
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

    /**
     * @internal
     */
    public function getWebhooks(): ?WebhookCollection
    {
        $this->checkIfPropertyAccessIsAllowed('webhooks');

        return $this->webhooks;
    }

    /**
     * @internal
     */
    public function setWebhooks(WebhookCollection $webhooks): void
    {
        $this->webhooks = $webhooks;
    }
}
