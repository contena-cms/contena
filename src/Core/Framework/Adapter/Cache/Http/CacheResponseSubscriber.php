<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @phpstan-import-type CacheAttributeType from CacheAttribute
 */
class CacheResponseSubscriber implements EventSubscriberInterface
{
    private const string POLICY_AREA_FRONTEND = 'frontend';
    private const string POLICY_AREA_CHANNEL_API = 'channel_api';

    public function __construct(
        private readonly bool $httpCacheEnabled,
        private readonly MaintenanceModeResolver $maintenanceResolver,
        private readonly CacheHeadersService $cacheHeadersService,
        private readonly CachePolicyProvider $policyProvider,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
        ];
    }

    public function setResponseCache(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof ChannelContext) {
            return;
        }

        $this->cacheHeadersService->applyCacheHeaders($context, $response);

        $area = $this->isChannelApi($request) ? self::POLICY_AREA_CHANNEL_API : self::POLICY_AREA_FRONTEND;

        if (!$this->httpCacheEnabled) {
            if ($request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE)) {
                $this->applyPolicy($request, $response, $area, false, null);
            }

            return;
        }

        if (!$this->maintenanceResolver->shouldBeCached($request)) {
            $this->applyPolicy($request, $response, $area, false, null);

            return;
        }

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            $this->applyPolicy($request, $response, $area, false, null);

            return;
        }

        /** @var CacheAttributeType $cacheAttributeValue */
        $cacheAttributeValue = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        $cacheAttribute = CacheAttribute::fromAttributeValue($cacheAttributeValue);

        $cacheHashEvent = $this->cacheHeadersService->applyCacheHash($request, $context, $response);

        if (!$request->isMethod(Request::METHOD_GET) || $cacheAttribute === null) {
            $this->applyPolicy($request, $response, $area, false, null);

            return;
        }

        if ($cacheHashEvent && !$cacheHashEvent->shouldResponseBeCached()) {
            $response->headers->set(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS, '1');
            $this->applyPolicy($request, $response, $area, false, null);

            return;
        }

        $clientHash = $request->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE)
            ?? $request->cookies->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, '');
        $expectedHash = $cacheHashEvent?->getHash() ?? '';

        if ($clientHash !== $expectedHash) {
            $response->headers->set(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS, '1');
            $this->applyPolicy($request, $response, $area, false, null);

            return;
        }

        $this->applyPolicy($request, $response, $area, true, $cacheAttribute);
    }

    public function setResponseCacheHeader(ResponseEvent $event): void
    {
        if (!$this->httpCacheEnabled) {
            return;
        }

        /** @var CacheAttributeType $cache */
        $cache = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        if (!$cache) {
            return;
        }

        $event->getResponse()->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');
    }

    private function applyPolicy(
        Request $request,
        Response $response,
        string $area,
        bool $cacheable,
        ?CacheAttribute $cacheAttribute,
    ): void {
        $route = (string) $request->attributes->get('_route', '');
        $enforceNoStore = $request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE);

        $policy = $this->policyProvider->getPolicy($route, $area, $cacheable, $cacheAttribute, $enforceNoStore);

        $response->headers->remove('cache-control');
        $response->setCache($policy->cacheControl->toArray());
    }

    private function isChannelApi(Request $request): bool
    {
        return \in_array(
            ChannelApiRouteScope::ID,
            $request->attributes->all(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE),
            true,
        );
    }
}
