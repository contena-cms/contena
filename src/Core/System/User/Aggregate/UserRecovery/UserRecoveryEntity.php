<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserRecovery;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\User\UserEntity;

class UserRecoveryEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $userId;

    protected string $hash;

    protected ?UserEntity $user = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getHash(): string
    {
        $this->checkIfPropertyAccessIsAllowed('hash');

        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
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
