<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserConfig;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\User\UserEntity;

class UserConfigEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $key;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $value = null;

    protected string $userId;

    protected ?UserEntity $user = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

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
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }
}
