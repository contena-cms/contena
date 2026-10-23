<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Snippet\Channel\ChannelSnippetLoader;
use Contena\Core\System\Snippet\Channel\SnippetRoute;
use Contena\Core\System\Snippet\Channel\SnippetRouteResponse;
use Contena\Core\System\Snippet\Channel\SnippetSetResult;
use Contena\Core\System\Snippet\Channel\SnippetSetResultList;
use Contena\Core\System\Snippet\Extension\SnippetRouteExtension;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SnippetRoute::class)]
class SnippetRouteTest extends TestCase
{
    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $this->channelContext = Generator::generateChannelContext(
            baseContext: new Context(new ChannelApiSource(Uuid::randomHex())),
            channel: $channel
        );
    }

    public function testLoadParsesTheQueryAndWrapsTheLoaderResult(): void
    {
        $result = $this->createResult();

        $loader = $this->createMock(ChannelSnippetLoader::class);
        $loader->expects($this->once())
            ->method('load')
            ->with(['language-one', 'language-two'], ['checkout', 'account'], $this->channelContext)
            ->willReturn([$result]);

        $route = new SnippetRoute($loader, static::createStub(CacheTagCollector::class), new ExtensionDispatcher(new EventDispatcher()));

        // comma separated lists with whitespace and empty entries are translated into clean lists
        $request = new Request([
            'languageIds' => ' language-one, language-two,,',
            'prefixes' => 'checkout , account',
        ]);

        $response = $route->load($request, $this->channelContext);

        static::assertSame([$result], $response->getResult()->sets);
        // a single set keeps its own hash as etag
        static::assertSame('"' . $result->hash . '"', $response->getEtag());
    }

    public function testEtagOfMultipleSetsIsCombined(): void
    {
        $first = $this->createResult(hash: 'hash-one');
        $second = $this->createResult(hash: 'hash-two');

        $loader = static::createStub(ChannelSnippetLoader::class);
        $loader->method('load')->willReturn([$first, $second]);

        $route = new SnippetRoute($loader, static::createStub(CacheTagCollector::class), new ExtensionDispatcher(new EventDispatcher()));

        $response = $route->load(new Request(), $this->channelContext);

        static::assertSame('"' . Hasher::hash('hash-one-hash-two') . '"', $response->getEtag());
    }

    public function testReturnsNotModifiedWhenIfNoneMatchMatchesTheEtag(): void
    {
        $loader = static::createStub(ChannelSnippetLoader::class);
        $loader->method('load')->willReturn([$this->createResult()]);

        $route = new SnippetRoute($loader, static::createStub(CacheTagCollector::class), new ExtensionDispatcher(new EventDispatcher()));

        $etag = $route->load(new Request(), $this->channelContext)->getEtag();
        static::assertNotNull($etag);

        $request = new Request();
        $request->headers->set('If-None-Match', $etag);

        $response = $route->load($request, $this->channelContext);

        static::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        static::assertSame($etag, $response->getEtag());
    }

    public function testAddsACacheTagPerSnippetSet(): void
    {
        $first = $this->createResult(snippetSetId: 'set-one');
        $second = $this->createResult(snippetSetId: 'set-two');

        $loader = static::createStub(ChannelSnippetLoader::class);
        $loader->method('load')->willReturn([$first, $second]);

        $collectedTags = [];
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->exactly(2))
            ->method('addTag')
            ->willReturnCallback(function (string ...$tags) use (&$collectedTags): void {
                $collectedTags = [...$collectedTags, ...$tags];
            });

        $route = new SnippetRoute($loader, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));

        $route->load(new Request(), $this->channelContext);

        static::assertSame([Translator::tag('set-one'), Translator::tag('set-two')], $collectedTags);
    }

    public function testGetDecoratedThrows(): void
    {
        $route = new SnippetRoute(
            static::createStub(ChannelSnippetLoader::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $this->expectExceptionObject(new DecorationPatternException(SnippetRoute::class));

        $route->getDecorated();
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $response = new SnippetRouteResponse(new SnippetSetResultList());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('snippet-route.load.pre', function (SnippetRouteExtension $extension) use ($request, $response): void {
            static::assertSame(['request' => $request, 'context' => $this->channelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SnippetRoute(
            static::createStub(ChannelSnippetLoader::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $this->channelContext));
    }

    private function createResult(string $hash = 'test-hash', string $snippetSetId = 'test-set-id'): SnippetSetResult
    {
        return new SnippetSetResult(
            languageId: Uuid::randomHex(),
            locale: 'pl-PL',
            fallbackLocale: 'pl',
            snippetSetId: $snippetSetId,
            hash: $hash,
            snippets: ['checkout.cart.title' => 'Koszyk'],
        );
    }
}
