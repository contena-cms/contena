<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ConsentLog;

/**
 * The cookie banner configuration as it was presented to visitors, stored once per
 * data scope and configuration hash. A consent record references it through its
 * `configHash`, so it can be shown later what the visitor agreed to.
 */
final readonly class CookieConsentConfigSnapshot implements \JsonSerializable
{
    /**
     * @param list<mixed> $cookieGroups JSON-serializable cookie groups, as the banner received them
     */
    public function __construct(
        public string $dataScopeId,
        public string $configHash,
        public array $cookieGroups,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'dataScopeId' => $this->dataScopeId,
            'configHash' => $this->configHash,
            'cookieGroups' => $this->cookieGroups,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
