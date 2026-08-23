<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant\Resolver;

use Symfony\Component\HttpFoundation\Request;

/**
 * Iterates the registered tenant resolvers in priority order; the first
 * non-null resolution wins.
 *
 * @internal
 */
final class TenantResolverChain implements TenantResolverInterface
{
    public const string ID = 'chain';

    /**
     * @param iterable<TenantResolverInterface> $resolvers
     */
    public function __construct(private readonly iterable $resolvers)
    {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function resolve(Request $request): ?TenantResolution
    {
        foreach ($this->resolvers as $resolver) {
            $resolution = $resolver->resolve($request);

            if ($resolution !== null) {
                return $resolution;
            }
        }

        return null;
    }
}
