<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

class ResponseCacheConfiguration
{
    private bool $enabled = true;

    /**
     * Defines the max_age directive for client-side caching.
     */
    private ?int $clientMaxAge = null;

    /**
     * Defines the s_maxage directive for shared caches (e.g. CDNs). Also influences caching on the framework level.
     */
    private ?int $sharedMaxAge = null;

    /**
     * @var list<string>
     */
    private array $invalidationStates = [];

    /**
     * @var list<string>
     */
    private array $cacheTags = [];

    /**
     * Calling `disable()` during script execution, will prevent that the result will be cached.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * `clientMaxAge()` allows you to define the max_age directive for client-side caching.
     *
     * @param int $maxAge The maximum number of seconds this response should be cached on the client side.
     */
    public function clientMaxAge(int $maxAge): self
    {
        $this->clientMaxAge = $maxAge;

        return $this;
    }

    /**
     * `sharedMaxAge()` allows you to define the s_maxage directive for shared caches (e.g. CDNs). Also influences
     *  caching on the framework level (in cache pools).
     */
    public function sharedMaxAge(int $maxAge): self
    {
        $this->sharedMaxAge = $maxAge;

        return $this;
    }

    /**
     * `invalidationState()` allows you to define states when the cache should be ignored.
     *
     * @param string ...$invalidationStates The states when the cache should be ignored, e.g. "logged-in" or "cart-filled".
     */
    public function invalidationState(string ...$invalidationStates): self
    {
        $this->invalidationStates = array_values(array_merge($this->invalidationStates, $invalidationStates));

        return $this;
    }

    /**
     * `tag()` allows you to tag the cached response, so you can later invalidate it through a `cache-invalidation` script.
     *
     * @param string ...$cacheTags The tags of the cache item.
     */
    public function tag(string ...$cacheTags): self
    {
        $this->cacheTags = array_values(array_merge($this->cacheTags, $cacheTags));

        return $this;
    }

    /**
     * @internal
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @internal
     */
    public function getClientMaxAge(): ?int
    {
        return $this->clientMaxAge;
    }

    /**
     * @internal
     */
    public function getSharedMaxAge(): ?int
    {
        return $this->sharedMaxAge;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function getInvalidationStates(): array
    {
        return $this->invalidationStates;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function getCacheTags(): array
    {
        return $this->cacheTags;
    }
}
