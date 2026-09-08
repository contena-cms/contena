<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Adapter\Cache\CacheCompressor;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Api\ResponseFields;
use Contena\Core\System\Channel\ChannelContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ScriptChannelApiRoute
{
    public function __construct(
        private readonly ScriptExecutor $executor,
        private readonly ScriptResponseEncoder $scriptResponseEncoder,
        private readonly TagAwareAdapterInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(path: '/channel-api/script/{hook}', name: 'channel-api.script_endpoint', methods: ['GET', 'POST'], requirements: ['hook' => '.+'])]
    public function execute(string $hook, Request $request, ChannelContext $context): Response
    {
        //  blog/update =>  blog-update
        $hookName = \str_replace('/', '-', $hook);

        $hook = new ChannelApiHook($hookName, $request->request->all(), $request->query->all(), $context);

        $cacheKey = null;
        if ($request->isMethodCacheable()) {
            /** @var ChannelApiCacheKeyHook $cacheKeyHook */
            $cacheKeyHook = $hook->getFunction(ChannelApiCacheKeyHook::FUNCTION_NAME);

            $this->executor->execute($cacheKeyHook);

            $cacheKey = $cacheKeyHook->getCacheKey();
        }

        $cachedResponse = $this->readFromCache($cacheKey, $request);

        if ($cachedResponse) {
            return $cachedResponse;
        }

        /** @var ChannelApiResponseHook $responseHook */
        $responseHook = $hook->getFunction(ChannelApiResponseHook::FUNCTION_NAME);
        // hook: channel-api-{hook}
        $this->executor->execute($responseHook);

        $fields = new ResponseFields(
            RequestParamHelper::get($request, 'includes', []),
            RequestParamHelper::get($request, 'excludes', []),
        );

        $symfonyResponse = $this->scriptResponseEncoder->encodeToSymfonyResponse(
            $responseHook->getScriptResponse(),
            $fields,
            \str_replace('-', '_', 'channel_api_' . $hookName . '_response')
        );

        $cacheConfig = $responseHook->getScriptResponse()->getCache();
        if ($cacheKey && $cacheConfig->isEnabled()) {
            $this->storeResponse($cacheKey, $cacheConfig, $symfonyResponse);
        }

        return $symfonyResponse;
    }

    private function readFromCache(?string $cacheKey, Request $request): ?Response
    {
        if (!$cacheKey) {
            return null;
        }

        $item = $this->cache->getItem($cacheKey);

        try {
            if (!$item->isHit() || !$item->get()) {
                $this->logger->info('cache-miss: ' . $request->getPathInfo());

                return null;
            }

            /** @var Response $response */
            $response = CacheCompressor::uncompress($item);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return null;
        }

        $this->logger->info('cache-hit: ' . $request->getPathInfo());

        return $response;
    }

    private function storeResponse(string $cacheKey, ResponseCacheConfiguration $cacheConfig, Response $symfonyResponse): void
    {
        $item = $this->cache->getItem($cacheKey);

        $item = CacheCompressor::compress($item, $symfonyResponse);

        $item->tag($cacheConfig->getCacheTags());
        $item->expiresAfter($cacheConfig->getSharedMaxAge());

        $this->cache->save($item);
    }
}
