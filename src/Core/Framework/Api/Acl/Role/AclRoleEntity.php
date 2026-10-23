<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl\Role;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\AdminUserCollection;

/**
 * @codeCoverageIgnore
 */
class AclRoleEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected ?string $description = null;

    /**
     * @var list<string>
     */
    protected array $privileges = [];

    protected ?AdminUserCollection $users = null;

    protected ?AppEntity $app = null;

    protected ?IntegrationCollection $integrations = null;

    protected ?\DateTimeInterface $deletedAt = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUsers(): ?AdminUserCollection
    {
        return $this->users;
    }

    public function setUsers(AdminUserCollection $users): void
    {
        $this->users = $users;
    }

    /**
     * @return list<string>
     */
    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    /**
     * @param list<string> $privileges
     */
    public function setPrivileges(array $privileges): void
    {
        $this->privileges = $privileges;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getIntegrations(): ?IntegrationCollection
    {
        return $this->integrations;
    }

    public function setIntegrations(IntegrationCollection $integrations): void
    {
        $this->integrations = $integrations;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeInterface $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }
}
