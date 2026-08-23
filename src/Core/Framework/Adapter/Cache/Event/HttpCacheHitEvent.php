<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Event;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

class HttpCacheHitEvent extends Event
{
    public function __construct(
        public readonly CacheItemInterface $item,
        public readonly Request $request,
        public readonly Response $response
    ) {
    }
}
