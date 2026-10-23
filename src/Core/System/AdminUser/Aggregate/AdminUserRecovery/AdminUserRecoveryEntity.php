<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\AdminUser\AdminUserEntity;

class AdminUserRecoveryEntity extends Entity
{
    use EntityIdTrait;

    protected string $admin_userId;

    protected string $hash;

    protected ?AdminUserEntity $admin_user = null;

    public function getUserId(): string
    {
        return $this->admin_userId;
    }

    public function setUserId(string $admin_userId): void
    {
        $this->admin_userId = $admin_userId;
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

    public function getUser(): ?AdminUserEntity
    {
        return $this->admin_user;
    }

    public function setUser(AdminUserEntity $admin_user): void
    {
        $this->admin_user = $admin_user;
    }
}
