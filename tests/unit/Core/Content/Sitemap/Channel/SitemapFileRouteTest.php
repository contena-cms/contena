<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Channel;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Channel\SitemapFileRoute;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Tests\Examples\GetSitemapFileExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SitemapFileRoute::class)]
class SitemapFileRouteTest extends TestCase
{
    public function testExtension(): void
    {
        $fileSystem = static::createStub(FilesystemOperator::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new GetSitemapFileExample());

        $extensionDispatcher = new ExtensionDispatcher($dispatcher);

        $route = new SitemapFileRoute($fileSystem, $extensionDispatcher);

        $request = new Request();
        $context = static::createStub(ChannelContext::class);
        $filePath = 'test.xml.gz';

        $response = $route->getSitemapFile($request, $context, $filePath);

        static::assertSame('Hello World!', $response->getContent());
    }
}
