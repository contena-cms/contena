<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

use Contena\Core\Defaults;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * Immutable identity of the platform or tenant that owns business data.
 *
 * Tenant scope ids intentionally equal their tenant ids. This keeps scope
 * propagation self-contained while the `data_scope` table remains the single
 * foreign-key target for all scoped business data.
 *
 * @internal
 */
final readonly class DataScope
{
    public function __construct(
        private string $id,
        private DataScopeType $type,
    ) {
        if (!Uuid::isValid($id)) {
            throw FrameworkException::invalidArgumentException('Data scope id must be a valid UUID.');
        }

        if ($type === DataScopeType::Platform && $id !== Defaults::PLATFORM_DATA_SCOPE) {
            throw FrameworkException::invalidArgumentException('The platform data scope must use the canonical platform scope id.');
        }

        if ($type === DataScopeType::Tenant && $id === Defaults::PLATFORM_DATA_SCOPE) {
            throw FrameworkException::invalidArgumentException('The platform data scope id can not identify a tenant scope.');
        }
    }

    public static function platform(): self
    {
        return new self(Defaults::PLATFORM_DATA_SCOPE, DataScopeType::Platform);
    }

    public static function tenant(string $tenantId): self
    {
        return new self($tenantId, DataScopeType::Tenant);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): DataScopeType
    {
        return $this->type;
    }

    public function isPlatform(): bool
    {
        return $this->type === DataScopeType::Platform;
    }

    public function isTenant(): bool
    {
        return $this->type === DataScopeType::Tenant;
    }

    public function getTenantId(): ?string
    {
        return $this->isTenant() ? $this->id : null;
    }
}
