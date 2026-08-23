<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Snippet\SnippetService;
use Contena\Core\Test\Generator;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tests for the language inheritance fix in snippet fallback.
 *
 * This test verifies that when a language is configured with a parent language
 * (e.g., Spanish inherits from English), the catalogue fallback chain is set up
 * correctly so that snippets fall back to the parent language.
 *
 * The key behavior:
 * - Each catalogue in the chain loads its own locale's snippets
 * - The fallback mechanism works through the catalogue chain (e.g., es-ES -> en-GB)
 * - This ensures Spanish views show English snippets when Spanish doesn't exist,
 *   instead of falling back to the system default
 *
 * @internal
 */
#[CoversClass(Translator::class)]
class TranslatorLanguageInheritanceTest extends TestCase
{
    /**
     * Tests that when a language has a configured parent (via language chain),
     * the catalogue fallback chain is set up correctly using language inheritance.
     *
     * Scenario:
     * - Current language: Spanish (es-ES) with parent English (en-GB)
     * - Language chain: [spanish-uuid, english-uuid]
     * - Expected: es-ES catalogue has en-GB as fallback catalogue
     * - Each catalogue loads its own locale's snippets
     */
    public function testGetCatalogueUsesLanguageInheritanceForFallback(): void
    {
        $spanishLanguageId = Uuid::randomHex();
        $englishLanguageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();

        $decorated = static::createStub(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('es-ES', [
            'messages' => [
                'global.title' => 'Title in catalogue',
            ],
        ]);
        $fallbackCatalogue = new MessageCatalogue('en-GB', [
            'messages' => [
                'global.title' => 'English title',
            ],
        ]);

        // Return different catalogues for different locales to avoid circular reference
        $decorated->method('getCatalogue')->willReturnCallback(
            static fn (?string $locale = null) => $locale === 'en-GB' ? $fallbackCatalogue : $originCatalogue
        );
        $decorated->method('getLocale')->willReturn('es-ES');

        $channelContext = self::createChannelContext([$spanishLanguageId, $englishLanguageId]);

        // Create request with ChannelContext
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $snippetSetId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        // Mock LanguageLocaleCodeProvider to return en-GB for the parent language
        $localeCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $localeCodeProvider->method('getLocaleForLanguageId')->willReturn('en-GB');
        $localeCodeProvider->method('getParentLanguageLocalesForLanguageId')->willReturn(['en-GB']);

        // Each catalogue loads its own locale's snippets
        // The fallback mechanism works through the catalogue chain (es-ES -> en-GB)
        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->atLeastOnce())
            ->method('getFrontendSnippets')
            ->willReturn([
                'global.title' => 'Title from snippets',
            ]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $cache = static::createStub(CacheInterface::class);
        $item = new CacheItem();
        $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
        $property->setValue($item, true);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });

        $translator = new Translator(
            $decorated,
            $requestStack,
            $cache,
            static::createStub(MessageFormatterInterface::class),
            $connection,
            $localeCodeProvider,
            $snippetService,
            static::createStub(CacheTagCollector::class),
        );

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        $catalogue = $translator->getCatalogue('es-ES');

        // Verify catalogue was created with correct locale
        static::assertSame('es-ES', $catalogue->getLocale());

        // Verify the language inheritance fallback chain is set up (es-ES -> en-GB)
        $fallback = $catalogue->getFallbackCatalogue();
        static::assertNotNull($fallback, 'Expected en-GB fallback catalogue based on language inheritance');
        static::assertSame('en-GB', $fallback->getLocale());
    }

    /**
     * Tests that when no ChannelContext is available,
     * the translator falls back to the original behavior (locale prefix).
     */
    public function testGetCatalogueFallsBackToLocalePrefixWithoutChannelContext(): void
    {
        $snippetSetId = Uuid::randomHex();

        $decorated = static::createStub(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('es-ES', [
            'messages' => [
                'global.title' => 'Title in catalogue',
            ],
        ]);

        $decorated->method('getCatalogue')->willReturn($originCatalogue);
        $decorated->method('getLocale')->willReturn('es-ES');

        // Create request WITHOUT ChannelContext
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $snippetSetId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $localeCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);

        // Each catalogue loads its own locale's snippets
        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->once())
            ->method('getFrontendSnippets')
            ->with(
                static::anything(),
                $snippetSetId,
                'es-ES',
                static::anything()
            )
            ->willReturn([
                'global.title' => 'Title from snippets',
            ]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $cache = static::createStub(CacheInterface::class);
        $item = new CacheItem();
        $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
        $property->setValue($item, true);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });

        $translator = new Translator(
            $decorated,
            $requestStack,
            $cache,
            static::createStub(MessageFormatterInterface::class),
            $connection,
            $localeCodeProvider,
            $snippetService,
            static::createStub(CacheTagCollector::class),
        );

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        static::assertSame('es-ES', $translator->getCatalogue('es-ES')->getLocale());
    }

    /**
     * Tests that when language chain has only one language (no parent),
     * the translator falls back to the original behavior (locale prefix).
     */
    public function testGetCatalogueFallsBackToLocalePrefixWithNoParentLanguage(): void
    {
        $englishLanguageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();

        $decorated = static::createStub(SymfonyTranslator::class);
        $originCatalogue = new MessageCatalogue('en-GB', [
            'messages' => [
                'global.title' => 'Title in catalogue',
            ],
        ]);

        $decorated->method('getCatalogue')->willReturn($originCatalogue);
        $decorated->method('getLocale')->willReturn('en-GB');

        $channelContext = self::createChannelContext([$englishLanguageId]);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $snippetSetId);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $localeCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $localeCodeProvider->method('getParentLanguageLocalesForLanguageId')->willReturn([]);

        // Each catalogue loads its own locale's snippets
        $snippetService = $this->createMock(SnippetService::class);
        $snippetService->expects($this->once())
            ->method('getFrontendSnippets')
            ->with(
                static::anything(),
                $snippetSetId,
                'en-GB',
                static::anything()
            )
            ->willReturn([
                'global.title' => 'Title from snippets',
            ]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$snippetSetId]);

        $cache = static::createStub(CacheInterface::class);
        $item = new CacheItem();
        $property = new \ReflectionProperty(CacheItem::class, 'isTaggable');
        $property->setValue($item, true);

        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });

        $translator = new Translator(
            $decorated,
            $requestStack,
            $cache,
            static::createStub(MessageFormatterInterface::class),
            $connection,
            $localeCodeProvider,
            $snippetService,
            static::createStub(CacheTagCollector::class),
        );

        $snippetSetIdProp = new \ReflectionProperty(Translator::class, 'snippetSetId');
        $snippetSetIdProp->setValue($translator, $snippetSetId);

        static::assertSame('en-GB', $translator->getCatalogue('en-GB')->getLocale());
    }

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private static function createChannelContext(array $languageIdChain): ChannelContext
    {
        return Generator::generateChannelContext(
            baseContext: new Context(new SystemSource(), $languageIdChain),
            token: 'token',
        );
    }
}
