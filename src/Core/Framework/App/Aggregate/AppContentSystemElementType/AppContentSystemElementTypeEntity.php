<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppContentSystemElementType;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @internal
 */
class AppContentSystemElementTypeEntity extends Entity
{
    use EntityIdTrait;

    protected string $appId;

    protected string $name;

    /**
     * @var array<string, mixed>
     */
    protected array $schema;

    protected string $hash;

    protected ?AppEntity $app = null;

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function setSchema(array $schema): void
    {
        $this->schema = $schema;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }
}
