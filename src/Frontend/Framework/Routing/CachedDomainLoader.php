<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Frontend\Framework\Routing\Struct\DomainCollection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

class CachedDomainLoader extends AbstractDomainLoader implements ResetInterface
{
    final public const string DOMAIN_COLLECTION_CACHE_KEY = 'routing-domain-collection';

    private ?DomainCollection $domainCollection = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractDomainLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    public function getDecorated(): AbstractDomainLoader
    {
        return $this->decorated;
    }

    public function loadDomains(): DomainCollection
    {
        if ($this->domainCollection !== null) {
            return $this->domainCollection;
        }

        $fresh = null;

        $value = $this->cache->get(self::DOMAIN_COLLECTION_CACHE_KEY, function (ItemInterface $item) use (&$fresh): string {
            $fresh = $this->getDecorated()->loadDomains();

            return CacheValueCompressor::compress($fresh);
        });

        // the domains were loaded in this call, return them directly instead of
        // uncompressing the cache payload that was just compressed from them
        if ($fresh instanceof DomainCollection) {
            return $this->domainCollection = $fresh;
        }

        /** @var DomainCollection $value */
        $value = CacheValueCompressor::uncompress($value);

        return $this->domainCollection = $value;
    }

    public function reset(): void
    {
        $this->domainCollection = null;
    }
}
