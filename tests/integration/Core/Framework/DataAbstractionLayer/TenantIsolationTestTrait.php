<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Contena\Core\Framework\Context;

/**
 * Reusable tenant isolation assertions for tenant-scoped entities: a write
 * from a tenant context is visible to that tenant,
 * invisible to other tenants, and visible to the legacy and platform global
 * contexts.
 *
 * @internal
 */
trait TenantIsolationTestTrait
{
    private function seedTenant(string $code): string
    {
        return $this->createTenant('Tenant ' . $code, $code . '-' . \bin2hex(\random_bytes(4)))->id;
    }

    /**
     * @param \Closure(string): mixed $write Receives tenant A's id and must create the entity under test.
     * @param \Closure(Context): int $count Receives the read context and must return the amount of probe entities.
     */
    private function assertTenantIsolated(string $tenantA, string $tenantB, \Closure $write, \Closure $count): void
    {
        $write($tenantA);

        static::assertSame(1, $count(Context::createTenantContext($tenantA)));
        static::assertSame(0, $count(Context::createTenantContext($tenantB)));
        static::assertSame(1, $count(Context::createGlobalContext()));
        static::assertSame(0, $count(Context::createDefaultContext()));
    }
}
