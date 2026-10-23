<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @codeCoverageIgnore
 */
class AdminUserAccessKeyEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $admin_userId;

    protected string $accessKey;

    protected string $secretAccessKey;

    protected ?\DateTimeInterface $lastUsageAt = null;

    protected ?AdminUserEntity $admin_user = null;

    public function getUserId(): string
    {
        return $this->admin_userId;
    }

    public function setUserId(string $admin_userId): void
    {
        $this->admin_userId = $admin_userId;
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

    public function setUser(AdminUserEntity $admin_user): void
    {
        $this->admin_user = $admin_user;
    }
}
