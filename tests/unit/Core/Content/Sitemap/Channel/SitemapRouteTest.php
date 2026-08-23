<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Channel\SitemapRoute;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Content\Sitemap\Service\SitemapListerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SitemapRoute::class)]
class SitemapRouteTest extends TestCase
{
    public function testReadsTheRefreshStrategyFromTheChannelTenant(): void
    {
        $context = Context::createTenantContext(Uuid::randomHex());
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn(Uuid::randomHex());
        $channelContext->method('getContext')->willReturn($context);

        $sitemapLister = $this->createMock(SitemapListerInterface::class);
        $sitemapLister->expects($this->once())
            ->method('getSitemaps')
            ->with($channelContext)
            ->willReturn([]);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getInt')
            ->with('core.sitemap.sitemapRefreshStrategy', null, $context)
            ->willReturn(SitemapExporterInterface::STRATEGY_MANUAL);

        $sitemapExporter = $this->createMock(SitemapExporterInterface::class);
        $sitemapExporter->expects($this->never())->method('generate');

        $route = new SitemapRoute(
            $sitemapLister,
            $systemConfigService,
            $sitemapExporter,
            static::createStub(CacheTagCollector::class),
        );

        $response = $route->load(new Request(), $channelContext);

        static::assertCount(0, $response->getSitemaps());
    }
}
