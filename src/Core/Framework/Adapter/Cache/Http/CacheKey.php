<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class CacheKey
{
    public function __construct(
        public readonly string $key,
        public readonly bool $isCacheable,
    ) {
    }
}
