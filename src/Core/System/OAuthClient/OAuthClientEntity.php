<?php declare(strict_types=1);

namespace Contena\Core\System\OAuthClient;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
class OAuthClientEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected bool $active = true;

    /**
     * @var list<string>
     */
    protected array $redirectUris = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * @return list<string>
     */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /**
     * @param list<string> $redirectUris
     */
    public function setRedirectUris(array $redirectUris): void
    {
        $this->redirectUris = $redirectUris;
    }
}
