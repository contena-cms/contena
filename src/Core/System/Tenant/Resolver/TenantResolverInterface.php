<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant\Resolver;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the tenant of a request from its addressing (domain, path or any
 * other convention). Resolvers are chained by priority; the first non-null
 * resolution wins. Register implementations with the `contena.tenant_resolver`
 * tag.
 */
interface TenantResolverInterface
{
    /**
     * Stable identifier of the resolution strategy, used as the source of
     * the resolution and for diagnostics.
     */
    public function getId(): string;

    /**
     * Returns the resolved tenant or null when this resolver does not apply
     * to the request. Implementations must not throw for unmatched requests.
     */
    public function resolve(Request $request): ?TenantResolution;
}
