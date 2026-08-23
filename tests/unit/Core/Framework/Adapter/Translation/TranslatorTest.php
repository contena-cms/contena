<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Snippet\SnippetService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(Translator::class)]
class TranslatorTest extends TestCase
{
    #[DataProvider('getCatalogueRequestProvider')]
    public function testGetCatalogueIsCachedCorrectly(?string $snippetSetId, ?Request $request, ?string $expectedCacheKey, ?string $injectChannelId = null): void
    {
        $decorated = static::createStub(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('en-GB', [
            'messages' => [
                'global.title' => 'This is a title',
                'global.summary' => 'This is a summary',
            ],
        ]);

        $decorated->method('getCatalogue')->willReturn($originCatalogue);
        $decorated->method('getLocale')->willReturn('en-GB');

        $requestStack = new RequestStack();

        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        $cache = $this->createMock(CacheInterface::class);

        $snippetServiceMock = $this->createMock(SnippetService::class);

        if ($expectedCacheKey !== null) {
            $snippetServiceMock->expects($this->once())->method('getFrontendSnippets')->willReturn([
                'global.title' => 'This is overrided title',
                'global.description' => 'Description',
            ]);
        } else {
            $snippetServiceMock->expects($this->never())->method('getFrontendSnippets');
        }

        $localeCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $localeCodeProvider->method('getLocaleForLanguageId')->willReturn('en-GB');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $translator = new Translator(
            $decorated,
            $requestStack,
            $cache,
            static::createStub(MessageFormatterInterface::class),
            $connection,
            $localeCodeProvider,
            $snippetServiceMock,
            static::createStub(CacheTagCollector::class),
        );

        $item = new CacheItem();
        $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
        $property->setValue($item, true);

        $cache->expects($expectedCacheKey ? $this->once() : $this->never())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($expectedCacheKey, $item) {
            static::assertSame($expectedCacheKey, $key);

            return $callback($item);
        });

        if ($injectChannelId) {
            $translator->injectSettings($injectChannelId, Uuid::randomHex(), 'en-GB', Context::createDefaultContext());
        }

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        // No snippet is added
        if ($expectedCacheKey === null) {
            $catalogue = $translator->getCatalogue('en-GB');

            static::assertSame($originCatalogue, $catalogue);

            return;
        }

        $catalogue = $translator->getCatalogue('en-GB');

        static::assertNotSame($originCatalogue, $catalogue);
        static::assertSame([
            'global.title' => 'This is overrided title',
            'global.summary' => 'This is a summary',
            'global.description' => 'Description',
        ], $catalogue->all('messages'));
    }

    /**
     * @param string[] $dbSnippetSetIds
     */
    #[DataProvider('getSnippetSetIdRequestProvider')]
    public function testGetSnippetId(array $dbSnippetSetIds, ?string $expectedSnippetSetId, ?string $locale, ?string $requestSnippetSetId): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(self::createRequest(null, $requestSnippetSetId));

        $connection = $this->createMock(Connection::class);
        $connection->expects($locale ? $this->once() : $this->never())->method('fetchFirstColumn')->willReturn($dbSnippetSetIds);

        $translator = new Translator(
            static::createStub(SymfonyTranslator::class),
            $requestStack,
            static::createStub(CacheInterface::class),
            static::createStub(MessageFormatterInterface::class),
            $connection,
            static::createStub(LanguageLocaleCodeProvider::class),
            static::createStub(SnippetService::class),
            static::createStub(CacheTagCollector::class),
        );

        $snippetSetId = $translator->getSnippetSetId($locale);

        static::assertSame($expectedSnippetSetId, $snippetSetId);

        // double call to make sure caching works
        $snippetSetId = $translator->getSnippetSetId($locale);

        static::assertSame($expectedSnippetSetId, $snippetSetId);
    }

    public function testGetSnippetIdUsingInjectSetting(): void
    {
        $requestStack = new RequestStack();
        $domainSnippetSetId = Uuid::randomHex();
        $injectSnippetSetId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))->method('fetchFirstColumn')->willReturn([$injectSnippetSetId, $domainSnippetSetId]);

        $key1 = \sprintf('translation.catalog.%s.%s', TestDefaults::CHANNEL, $injectSnippetSetId);
        $key2 = \sprintf('translation.catalog.%s.%s', TestDefaults::CHANNEL, $domainSnippetSetId);
        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->once())->method('findSnippetSetId')->with(TestDefaults::CHANNEL, Defaults::LANGUAGE_SYSTEM, 'en-GB')->willReturn($injectSnippetSetId);

        $translator = new Translator(
            static::createStub(SymfonyTranslator::class),
            $requestStack,
            new ArrayCache([
                $key1 => [],
                $key2 => [],
            ]),
            static::createStub(MessageFormatterInterface::class),
            $connection,
            static::createStub(LanguageLocaleCodeProvider::class),
            $snippetService,
            static::createStub(CacheTagCollector::class),
        );

        $translator->injectSettings(TestDefaults::CHANNEL, Defaults::LANGUAGE_SYSTEM, 'en-GB', Context::createDefaultContext());

        static::assertSame($injectSnippetSetId, $translator->getSnippetSetId('en-GB'));

        // prioritize snippet from channel domain if set
        $requestStack->push(self::createRequest(TestDefaults::CHANNEL, $domainSnippetSetId));
        $translator->reset();
        static::assertSame($domainSnippetSetId, $translator->getSnippetSetId('en-GB'));
    }

    public function testResetRestoresConfiguredFallbackLocalesAndLocale(): void
    {
        $decorated = $this->createMock(SymfonyTranslator::class);
        $decorated->method('getLocale')->willReturn('en_GB');
        $decorated->method('getFallbackLocales')->willReturn(['de-DE', 'en-GB', 'en']);

        $decorated->expects($this->once())
            ->method('setFallbackLocales')
            ->with(['de_DE', 'en_GB', 'en']);

        $decorated->expects($this->once())
            ->method('setLocale')
            ->with('en_GB');

        $translator = new Translator(
            $decorated,
            new RequestStack(),
            static::createStub(CacheInterface::class),
            static::createStub(MessageFormatterInterface::class),
            static::createStub(Connection::class),
            static::createStub(LanguageLocaleCodeProvider::class),
            static::createStub(SnippetService::class),
            static::createStub(CacheTagCollector::class),
        );

        $translator->reset();
    }

    public function testGetCatalogueUsesFallbackLocaleOutsideProd(): void
    {
        $snippetSetId = Uuid::randomHex();

        $decorated = static::createStub(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('de-DE', [
            'messages' => [
                'hello' => 'Hello',
            ],
        ]);
        $fallbackCatalogue = new MessageCatalogue('de', [
            'messages' => [
                'hello' => 'Hello',
            ],
        ]);
        $originCatalogue->addFallbackCatalogue($fallbackCatalogue);

        $decorated->method('getCatalogue')->willReturnMap([
            ['de-DE', $originCatalogue],
            ['de', $fallbackCatalogue],
        ]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->exactly(2))
            ->method('get')
            ->with(static::callback(
                static fn (string $key): bool => \in_array($key, [
                    \sprintf('translation.catalog.DEFAULT.%s-de-DE', $snippetSetId),
                    \sprintf('translation.catalog.DEFAULT.%s-de', $snippetSetId),
                ], true)
            ), static::isCallable())
            ->willReturnCallback(static function (string $_key, callable $callback) {
                $item = new CacheItem();
                $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
                $property->setValue($item, true);

                return $callback($item);
            });

        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->exactly(2))
            ->method('getFrontendSnippets')
            ->with(
                static::isInstanceOf(MessageCatalogue::class),
                $snippetSetId,
                static::callback(static fn (string $locale): bool => \in_array($locale, ['de-DE', 'de'], true)),
                null
            )
            ->willReturn([]);

        $localeCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $localeCodeProvider->method('getLocaleForLanguageId')->willReturn('de-DE');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $translator = new Translator(
            $decorated,
            new RequestStack(),
            $cache,
            static::createStub(MessageFormatterInterface::class),
            $connection,
            $localeCodeProvider,
            $snippetService,
            static::createStub(CacheTagCollector::class),
        );

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        $translator->getCatalogue('de-DE');
    }

    /**
     * @return iterable<string, array<int, string|Request|null>>
     */
    public static function getCatalogueRequestProvider(): iterable
    {
        $snippetSetId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        yield 'without request' => [
            $snippetSetId,
            null,
            \sprintf('translation.catalog.%s.%s-en-GB', 'DEFAULT', $snippetSetId),
        ];
        yield 'without snippetSetId' => [
            null,
            self::createRequest($channelId, null),
            null,
        ];

        yield 'without channelId' => [
            $snippetSetId,
            self::createRequest(null, $snippetSetId),
            \sprintf('translation.catalog.%s.%s-en-GB', 'DEFAULT', $snippetSetId),
        ];

        yield 'with injectSettings' => [
            $snippetSetId,
            null,
            \sprintf('translation.catalog.%s.%s-en-GB', $channelId, $snippetSetId),
            $channelId, // Inject channelId using injectSettings method
        ];
    }

    /**
     * @return iterable<string, array<string, string|string[]|null>>
     */
    public static function getSnippetSetIdRequestProvider(): iterable
    {
        $expectedSnippetSetId = Uuid::randomHex();
        $foundSnippetSetId = Uuid::randomHex();

        yield 'without locale and request snippet set id' => [
            'dbSnippetSetIds' => [],
            'expectedSnippetSetId' => null,
            'locale' => null,
            'requestSnippetSetId' => null,
        ];

        yield 'without locale but request snippet set id is set' => [
            'dbSnippetSetIds' => [],
            'expectedSnippetSetId' => $expectedSnippetSetId,
            'locale' => null,
            'requestSnippetSetId' => $expectedSnippetSetId,
        ];

        yield 'with locale and request snippet set id but no matched db record' => [
            'dbSnippetSetIds' => [],
            'expectedSnippetSetId' => $expectedSnippetSetId,
            'locale' => 'de-DE',
            'requestSnippetSetId' => $expectedSnippetSetId,
        ];

        yield 'with locale and there is one set matched' => [
            'dbSnippetSetIds' => [
                $foundSnippetSetId,
            ],
            'expectedSnippetSetId' => $foundSnippetSetId,
            'locale' => 'de-DE',
            'requestSnippetSetId' => $expectedSnippetSetId,
        ];

        yield 'with locale and multiple sets matched, take the first match' => [
            'dbSnippetSetIds' => [
                $foundSnippetSetId,
                Uuid::randomHex(),
            ],
            'expectedSnippetSetId' => $foundSnippetSetId,
            'locale' => 'de-DE',
            'requestSnippetSetId' => $expectedSnippetSetId,
        ];

        yield 'with locale and multiple sets matched, prioritize set from request' => [
            'dbSnippetSetIds' => [
                $foundSnippetSetId,
                $expectedSnippetSetId,
                Uuid::randomHex(),
            ],
            'expectedSnippetSetId' => $expectedSnippetSetId,
            'locale' => 'de-DE',
            'requestSnippetSetId' => $expectedSnippetSetId,
        ];
    }

    private static function createRequest(?string $channelId, ?string $snippetSetId): Request
    {
        return new Request(
            [],
            [],
            array_filter([
                ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID => $snippetSetId,
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelId,
            ]),
        );
    }
}

/**
 * @internal
 */
class ArrayCache implements CacheInterface
{
    /**
     * @param array<string, array{}> $cacheItems
     */
    public function __construct(private readonly array $cacheItems)
    {
    }

    /**
     * @param array<string, mixed>|null $metadata
     *
     * @return array{}
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): array
    {
        return $this->cacheItems[$key];
    }

    public function delete(string $key): bool
    {
        // Not needed in this test
        return true;
    }
}
