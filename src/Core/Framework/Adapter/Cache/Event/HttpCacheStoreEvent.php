<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Event;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

class HttpCacheStoreEvent extends Event
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly CacheItemInterface $item,
        public array $tags,
        public readonly Request $request,
        public readonly Response $response
    ) {
    }
}
