<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Channel;

use Contena\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Content\Sitemap\Service\SitemapListerInterface;
use Contena\Core\Content\Sitemap\Struct\SitemapCollection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class SitemapRoute extends AbstractSitemapRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SitemapListerInterface $sitemapLister,
        private readonly SystemConfigService $systemConfigService,
        private readonly SitemapExporterInterface $sitemapExporter,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'sitemap-route-' . $id;
    }

    /**
     * Though this is a GET route, caching was not added as the system may use the SitemapExporterInterface::STRATEGY_LIVE
     * refresh strategy and caching may interfere with it. This route is also not normally called often.
     */
    #[Route(path: '/channel-api/sitemap', name: 'channel-api.sitemap', methods: ['GET', 'POST'])]
    public function load(Request $request, ChannelContext $context): SitemapRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getChannelId()));

        $sitemaps = $this->sitemapLister->getSitemaps($context);

        if ($this->systemConfigService->getInt('core.sitemap.sitemapRefreshStrategy', context: $context->getContext()) !== SitemapExporterInterface::STRATEGY_LIVE) {
            return new SitemapRouteResponse(new SitemapCollection($sitemaps));
        }

        // Close session to prevent session locking from waiting in case there is another request coming in
        if ($request->hasSession(true) && session_status() === \PHP_SESSION_ACTIVE) {
            $request->getSession()->save();
        }

        try {
            $this->generateSitemap($context, true);
        } catch (AlreadyLockedException) {
            // Silent catch, lock couldn't be acquired. Some other process already generates the sitemap.
        }

        $sitemaps = $this->sitemapLister->getSitemaps($context);

        return new SitemapRouteResponse(new SitemapCollection($sitemaps));
    }

    public function getDecorated(): AbstractSitemapRoute
    {
        throw new DecorationPatternException(self::class);
    }

    private function generateSitemap(ChannelContext $channelContext, bool $force, ?string $lastProvider = null, ?int $offset = null): void
    {
        $result = $this->sitemapExporter->generate($channelContext, $force, $lastProvider, $offset);
        if ($result->isFinish() === false) {
            $this->generateSitemap($channelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }
}
