<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

/**
 * Represents default cache policies configuration for an area (e.g., frontend, channel_api)
 *
 * @internal
 *
 * @phpstan-type DefaultPoliciesConfig array{
 *     cacheable?: string|null,
 *     uncacheable?: string|null
 * }
 */
readonly class DefaultPolicies
{
    public function __construct(
        public ?string $cacheablePolicyName = null,
        public ?string $uncacheablePolicyName = null,
    ) {
    }

    /**
     * @param DefaultPoliciesConfig $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cacheablePolicyName: $data['cacheable'] ?? null,
            uncacheablePolicyName: $data['uncacheable'] ?? null,
        );
    }
}
