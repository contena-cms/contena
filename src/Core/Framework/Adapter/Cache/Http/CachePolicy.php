<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Adapter\AdapterException;

/**
 * Represents a single HTTP cache policy with cache control directives.
 *
 * @internal
 *
 * @phpstan-import-type CacheControlDirectivesConfig from CacheControlDirectives
 *
 * @phpstan-type CachePolicyConfig array{
 *     headers: array{
 *         cache_control: CacheControlDirectivesConfig
 *     }
 * }
 */
readonly class CachePolicy
{
    public function __construct(
        public CacheControlDirectives $cacheControl,
    ) {
    }

    /**
     * Create from configuration array
     *
     * @param CachePolicyConfig $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['headers']['cache_control'])) {
            throw AdapterException::invalidCachePolicyConfiguration('missing required "headers.cache_control" configuration');
        }

        $cacheControl = CacheControlDirectives::fromArray($data['headers']['cache_control']);

        return new self(cacheControl: $cacheControl);
    }

    public function with(
        ?CacheControlDirectives $cacheControl = null,
    ): self {
        return new self(
            cacheControl: $cacheControl ?? $this->cacheControl,
        );
    }

    /**
     * Fallback no-store policy when policy cannot be resolved or NO_STORE is enforced.
     */
    public static function noStore(): self
    {
        return new self(
            cacheControl: new CacheControlDirectives(
                noStore: true,
                noCache: true,
                mustRevalidate: true,
                maxAge: 0,
            )
        );
    }
}
