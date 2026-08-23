<?php declare(strict_types=1);

namespace Contena\Core\System\Integration;

use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Notification\NotificationCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Contena\Core\System\Tenant\TenantEntity;

class IntegrationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?TenantEntity $tenant = null;

    protected string $label;

    protected string $accessKey;

    protected string $secretAccessKey;

    protected bool $admin;

    protected ?\DateTimeInterface $lastUsageAt = null;

    protected ?AclRoleCollection $aclRoles = null;

    protected ?\DateTimeInterface $deletedAt = null;

    protected ?StateMachineHistoryCollection $stateMachineHistoryEntries = null;

    protected ?NotificationCollection $createdNotifications = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenant(): ?TenantEntity
    {
        return $this->tenant;
    }

    public function setTenant(?TenantEntity $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getSecretAccessKey(): string
    {
        return $this->secretAccessKey;
    }

    public function setSecretAccessKey(string $secretAccessKey): void
    {
        $this->secretAccessKey = $secretAccessKey;
    }

    public function getLastUsageAt(): ?\DateTimeInterface
    {
        return $this->lastUsageAt;
    }

    public function setLastUsageAt(\DateTimeInterface $lastUsageAt): void
    {
        $this->lastUsageAt = $lastUsageAt;
    }

    public function getAclRoles(): ?AclRoleCollection
    {
        return $this->aclRoles;
    }

    public function setAclRoles(AclRoleCollection $aclRoles): void
    {
        $this->aclRoles = $aclRoles;
    }

    public function getAdmin(): bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): void
    {
        $this->admin = $admin;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeInterface $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
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
}
