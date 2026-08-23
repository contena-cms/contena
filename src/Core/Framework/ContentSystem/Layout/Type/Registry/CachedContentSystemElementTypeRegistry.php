<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Registry;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * @final
 */
class CachedContentSystemElementTypeRegistry extends AbstractContentSystemElementTypeRegistry
{
    private const CACHE_KEY = 'content_system.element_types';

    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $inner,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getDecorated(): AbstractContentSystemElementTypeRegistry
    {
        return $this->inner;
    }

    public function all(): array
    {
        return $this->cache->get(self::CACHE_KEY, fn () => $this->inner->all());
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->all());
    }

    public function get(string $name): ContentSystemElementTypeSpecification
    {
        return $this->all()[$name] ?? throw ContentSystemException::elementTypeNotFound($name);
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
