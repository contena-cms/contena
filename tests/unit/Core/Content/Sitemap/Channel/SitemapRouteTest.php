<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Channel\SitemapRoute;
use Contena\Core\Content\Sitemap\Channel\SitemapRouteResponse;
use Contena\Core\Content\Sitemap\Extension\SitemapRouteExtension;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Content\Sitemap\Service\SitemapListerInterface;
use Contena\Core\Content\Sitemap\Struct\SitemapCollection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SitemapRoute::class)]
class SitemapRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $response = new SitemapRouteResponse(new SitemapCollection());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('sitemap-route.load.pre', static function (SitemapRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SitemapRoute(
            static::createStub(SitemapListerInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(SitemapExporterInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context));
    }
}
