<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

/**
 * Value object extended for cache attribute in request
 *
 * @phpstan-type CacheAttributeArray array{ clientMaxAge?: int, sharedMaxAge?: int, maxAge?: int }
 * @phpstan-type CacheAttributeType CacheAttributeArray|bool|string|int|CacheAttribute
 *
 * @internal
 */
readonly class CacheAttribute
{
    public function __construct(
        public ?int $maxAge = null,
        public ?int $sMaxAge = null,
        public ?string $policyModifier = null,
    ) {
    }

    /**
     * @param CacheAttributeArray $attributeValue
     */
    public static function fromArray(array $attributeValue): self
    {
        return new self(
            maxAge: $attributeValue['clientMaxAge'] ?? null,
            sMaxAge: $attributeValue['sharedMaxAge'] ?? $attributeValue['maxAge'] ?? null,
        );
    }

    /**
     * @param CacheAttributeType $attributeValue
     */
    public static function fromAttributeValue(array|bool|string|int|CacheAttribute|null $attributeValue): ?self
    {
        if ($attributeValue instanceof CacheAttribute) {
            return $attributeValue;
        }

        if (\is_array($attributeValue)) {
            return self::fromArray($attributeValue);
        }

        // from XML route definitions string values can come
        $attributeValue = filter_var($attributeValue, \FILTER_VALIDATE_BOOLEAN);
        if ($attributeValue === true) {
            return new self();
        }

        return null;
    }
}
