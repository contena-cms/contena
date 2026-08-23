<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Tenant\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Tenant\Resolver\TenantResolution;
use Contena\Core\System\Tenant\Resolver\TenantResolverChain;
use Contena\Core\System\Tenant\Resolver\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(TenantResolverChain::class)]
class TenantResolverChainTest extends TestCase
{
    public function testReturnsTheFirstNonNullResolution(): void
    {
        $first = new TenantResolution('tenant-a', 'first');
        $second = new TenantResolution('tenant-b', 'second');

        $chain = new TenantResolverChain([
            $this->resolver(static fn () => null, 'first'),
            $this->resolver(static fn () => $first, 'second'),
            $this->resolver(static fn () => $second, 'third'),
        ]);

        static::assertSame($first, $chain->resolve(new Request()));
    }

    public function testReturnsNullWhenNoResolverMatches(): void
    {
        $chain = new TenantResolverChain([
            $this->resolver(static fn () => null, 'first'),
            $this->resolver(fn (): ?TenantResolution => null, 'second'),
        ]);

        static::assertNull($chain->resolve(new Request()));
    }

    /**
     * @param \Closure(): ?TenantResolution $resolver
     */
    private function resolver(\Closure $resolver, string $id): TenantResolverInterface
    {
        return new class($resolver, $id) implements TenantResolverInterface {
            public function __construct(
                private readonly \Closure $resolver,
                private readonly string $id,
            ) {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function resolve(Request $request): ?TenantResolution
            {
                return ($this->resolver)();
            }
        };
    }
}
