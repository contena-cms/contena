<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Cache;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
class CacheFinalizer
{
    public function __construct(
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function finalize(Request $request, RenderingCacheContext $cacheContext): void
    {
        if ($cacheContext->isDisabled()) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);

            return;
        }

        $this->cacheTagCollector->addTag(...$cacheContext->getTags());
    }
}
