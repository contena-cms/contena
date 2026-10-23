<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Event\SitemapFilterOpenTagEvent;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(SitemapFilterOpenTagEvent::class)]
class SitemapFilterOpenTagEventTest extends TestCase
{
    public function testFullOpenTagRendersEveryRegisteredNamespace(): void
    {
        $event = new SitemapFilterOpenTagEvent(static::createStub(ChannelContext::class));
        $event->setUrlsetNamespaces([
            'xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
            'xmlns:image' => 'http://www.google.com/schemas/sitemap-image/1.1',
        ]);

        static::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">',
            $event->getFullOpenTag(),
        );
    }

    public function testSetOpenTagReplacesTheTemplate(): void
    {
        $event = new SitemapFilterOpenTagEvent(static::createStub(ChannelContext::class));

        static::assertStringStartsWith('<?xml version="1.0"', $event->getOpenTag());

        $event->setOpenTag('<urlset %urlsetNamespaces%>');
        $event->addUrlsetNamespace('xmlns:custom', 'https://example.com/schema');

        static::assertSame(
            ['xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9', 'xmlns:custom' => 'https://example.com/schema'],
            $event->getUrlsetNamespaces(),
        );
        static::assertSame('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:custom="https://example.com/schema">', $event->getFullOpenTag());
    }

    public function testExposesTheChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);

        $event = new SitemapFilterOpenTagEvent($channelContext);

        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
