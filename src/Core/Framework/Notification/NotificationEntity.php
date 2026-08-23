<?php declare(strict_types=1);

namespace Contena\Core\Framework\Notification;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Integration\IntegrationEntity;
use Contena\Core\System\User\UserEntity;

class NotificationEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?string $createdByIntegrationId = null;

    protected ?IntegrationEntity $createdByIntegration = null;

    protected ?string $createdByUserId = null;

    protected ?UserEntity $createdByUser = null;

    protected bool $adminOnly;

    /**
     * @var array<string>
     */
    protected array $requiredPrivileges = [];

    protected string $status;

    protected string $message;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getCreatedByIntegrationId(): ?string
    {
        return $this->createdByIntegrationId;
    }

    public function setCreatedByIntegrationId(string $createdByIntegrationId): void
    {
        $this->createdByIntegrationId = $createdByIntegrationId;
    }

    public function getCreatedByIntegration(): ?IntegrationEntity
    {
        return $this->createdByIntegration;
    }

    public function setCreatedByIntegration(IntegrationEntity $createdByIntegration): void
    {
        $this->createdByIntegration = $createdByIntegration;
    }

    public function getCreatedByUserId(): ?string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(string $createdByUserId): void
    {
        $this->createdByUserId = $createdByUserId;
    }

    public function getCreatedByUser(): ?UserEntity
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(UserEntity $createdByUser): void
    {
        $this->createdByUser = $createdByUser;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }

    public function setAdminOnly(bool $adminOnly): void
    {
        $this->adminOnly = $adminOnly;
    }

    /**
     * @return array<string>
     */
    public function getRequiredPrivileges(): array
    {
        return $this->requiredPrivileges;
    }

    /**
     * @param array<string> $requiredPrivileges
     */
    public function setRequiredPrivileges(array $requiredPrivileges): void
    {
        $this->requiredPrivileges = $requiredPrivileges;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
