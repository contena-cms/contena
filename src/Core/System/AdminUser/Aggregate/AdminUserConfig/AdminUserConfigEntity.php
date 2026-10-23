<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserConfig;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

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

    protected string $adminUserId;

    protected ?AdminUserEntity $adminUser = null;

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
        return $this->adminUserId;
    }

    public function setUserId(string $adminUserId): void
    {
        $this->adminUserId = $adminUserId;
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
