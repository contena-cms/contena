<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
class AdminUserAccessKeyEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $adminUserId;

    protected string $accessKey;

    protected string $secretAccessKey;

    protected ?\DateTimeInterface $lastUsageAt = null;

    protected ?AdminUserEntity $adminUser = null;

    public function getUserId(): string
    {
        return $this->adminUserId;
    }

    public function setUserId(string $adminUserId): void
    {
        $this->adminUserId = $adminUserId;
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

    public function getUser(): ?AdminUserEntity
    {
        return $this->admin_user;
    }

    public function setUser(AdminUserEntity $adminUser): void
    {
        $this->admin_user = $adminUser;
    }
}
