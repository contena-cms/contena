<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent;

use Contena\Core\System\Consent\ConsentDefinition;

/**
 * @internal
 */
class TestDefinition implements ConsentDefinition
{
    /**
     * @param array<string> $permissions
     */
    public function __construct(
        private readonly string $name,
        private readonly string $scopeName,
        private readonly array $permissions = [],
        private readonly ?string $latestRevision = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getScopeName(): string
    {
        return $this->scopeName;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public function getRequiredPermissions(): array
    {
        return $this->permissions;
    }

    public function getLatestRevision(): ?string
    {
        return $this->latestRevision;
    }
}
