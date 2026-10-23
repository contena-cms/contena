<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AdminUserRecoveryEntity extends Entity
{
    use EntityIdTrait;

    protected string $adminUserId;

    protected string $hash;

    protected ?AdminUserEntity $adminUser = null;

    public function getUserId(): string
    {
        return $this->adminUserId;
    }

    public function setUserId(string $adminUserId): void
    {
        $this->adminUserId = $adminUserId;
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

    public function setUser(AdminUserEntity $adminUser): void
    {
        $this->admin_user = $adminUser;
    }
}
