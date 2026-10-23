<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserConfig;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @codeCoverageIgnore
 */
class AdminUserConfigEntity extends Entity
{
    use EntityIdTrait;

    protected string $key;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $value = null;

    protected string $admin_userId;

    protected ?AdminUserEntity $admin_user = null;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getValue(): ?array
    {
        return $this->value;
    }

    /**
     * @param array<string, mixed>|null $value
     */
    public function setValue(?array $value): void
    {
        $this->value = $value;
    }

    public function getUserId(): string
    {
        return $this->admin_userId;
    }

    public function setUserId(string $admin_userId): void
    {
        $this->admin_userId = $admin_userId;
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
