<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\App;

use Contena\Core\Content\Media\File\TrustedUrlResolver;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class StaticTrustedUrlResolverFactory
{
    /**
     * @param list<string> $allowedPrivateIpAddresses
     */
    public static function create(array $allowedPrivateIpAddresses): TrustedUrlResolver
    {
        return new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34'], allowedPrivateIps: $allowedPrivateIpAddresses);
    }
}
