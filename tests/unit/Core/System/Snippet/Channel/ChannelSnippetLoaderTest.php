<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Snippet\Channel\ChannelSnippetLoader;
use Contena\Core\System\Snippet\SnippetException;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @internal
 */
#[CoversClass(ChannelSnippetLoader::class)]
class ChannelSnippetLoaderTest extends TestCase
{
    private ChannelContext $channelContext;

    private string $snippetSetId;

    protected function setUp(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $this->channelContext = Generator::generateChannelContext(
            baseContext: new Context(new ChannelApiSource(Uuid::randomHex())),
            channel: $channel
        );

        $this->snippetSetId = Uuid::randomHex();
    }

    public function testLoadReturnsMergedSnippetsOfTheCatalogueChainSortedByKey(): void
    {
        $catalogue = new MessageCatalogue('pl-PL', ['messages' => [
            'zeta.key' => 'Z',
            'checkout.cart.title' => 'Koszyk',
        ]]);
        $catalogue->addFallbackCatalogue(new MessageCatalogue('en-GB', ['messages' => [
            'checkout.cart.title' => 'Cart',
            'only.fallback' => 'Fallback value',
        ]]));

        $loader = $this->createLoader(['pl-PL' => $catalogue]);

        $results = $loader->load([], [], $this->channelContext);

        // without language ids exactly one set is loaded: the context language
        static::assertCount(1, $results);
        $result = $results[0];

        static::assertSame($this->channelContext->getLanguageId(), $result->languageId);
        static::assertSame('pl-PL', $result->locale);
        static::assertSame('pl', $result->fallbackLocale);
        static::assertSame($this->snippetSetId, $result->snippetSetId);

        // the current locale wins over the fallback, fallback-only keys survive, keys are sorted
        static::assertSame(
            [
                'checkout.cart.title' => 'Koszyk',
                'only.fallback' => 'Fallback value',
                'zeta.key' => 'Z',
            ],
            $result->snippets
        );

        static::assertSame(Hasher::hash($result->snippets), $result->hash);
    }

    public function testPrefixesMatchWholeKeySegments(): void
    {
        $catalogue = new MessageCatalogue('pl-PL', ['messages' => [
            'checkout' => 'Root key',
            'checkout.cart.title' => 'Koszyk',
            'checkoutConfirm.title' => 'Same string prefix, other namespace',
            'account.login' => 'Login',
        ]]);

        $loader = $this->createLoader(['pl-PL' => $catalogue]);

        $results = $loader->load([], ['checkout'], $this->channelContext);

        static::assertSame(
            [
                'checkout' => 'Root key',
                'checkout.cart.title' => 'Koszyk',
            ],
            $results[0]->snippets
        );
    }

    public function testTrailingDotAndPrefixOrderDoNotChangeTheResult(): void
    {
        $catalogue = new MessageCatalogue('pl-PL', ['messages' => [
            'checkout.cart.title' => 'Koszyk',
            'account.login' => 'Login',
            'general.home' => 'Home',
        ]]);

        $loader = $this->createLoader(['pl-PL' => $catalogue]);

        $first = $loader->load([], ['checkout.', 'account'], $this->channelContext)[0];
        $second = $loader->load([], ['account.', 'checkout'], $this->channelContext)[0];

        static::assertSame($first->snippets, $second->snippets);
        static::assertSame($first->hash, $second->hash);
    }

    public function testThrowsWhenTooManyPrefixesAreRequested(): void
    {
        $loader = $this->createLoader([]);

        // one over the limit, after deduplication
        $prefixes = array_map(
            static fn (int $index): string => 'namespace' . $index,
            range(0, ChannelSnippetLoader::MAX_PREFIXES)
        );

        $this->expectExceptionObject(
            SnippetException::tooManyPrefixes(ChannelSnippetLoader::MAX_PREFIXES + 1, ChannelSnippetLoader::MAX_PREFIXES)
        );

        $loader->load([], $prefixes, $this->channelContext);
    }

    public function testMultipleLanguagesReturnSetsSortedByLanguageId(): void
    {
        $languageIds = [Uuid::randomHex(), Uuid::randomHex()];
        sort($languageIds);
        [$firstLanguageId, $secondLanguageId] = $languageIds;

        $catalogues = [
            'pl-PL' => new MessageCatalogue('pl-PL', ['messages' => ['checkout.cart.title' => 'Koszyk']]),
            'de-DE' => new MessageCatalogue('de-DE', ['messages' => ['checkout.cart.title' => 'Warenkorb']]),
        ];

        $loader = $this->createLoader(
            $catalogues,
            locales: [$firstLanguageId => 'pl-PL', $secondLanguageId => 'de-DE'],
            languageRepository: $this->createLanguageRepository([$languageIds])
        );

        // request order is reversed on purpose, the result is normalized to ascending language ids
        $results = $loader->load([$secondLanguageId, $firstLanguageId], [], $this->channelContext);

        static::assertCount(2, $results);
        static::assertSame($firstLanguageId, $results[0]->languageId);
        static::assertSame('pl-PL', $results[0]->locale);
        static::assertSame($secondLanguageId, $results[1]->languageId);
        static::assertSame('de-DE', $results[1]->locale);
    }

    public function testResetsTranslatorStateBetweenLanguagesSharingOneLocale(): void
    {
        $languageIds = [Uuid::randomHex(), Uuid::randomHex()];
        sort($languageIds);

        // the translator memoises snippet sets per locale, without a reset between the two
        // languages the second one would silently reuse the first language's snippet set
        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())->method('reset');
        $translator->method('getCatalogue')->willReturnCallback(
            static fn (?string $locale): MessageCatalogue => new MessageCatalogue((string) $locale)
        );
        $translator->method('getSnippetSetId')->willReturn($this->snippetSetId);

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider->method('getLocaleForLanguageId')->willReturn('de-DE');

        $loader = new ChannelSnippetLoader(
            $translator,
            $languageLocaleProvider,
            $this->createLanguageRepository([$languageIds])
        );

        $loader->load($languageIds, [], $this->channelContext);
    }

    public function testDoesNotResetTranslatorForASingleLanguage(): void
    {
        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->never())->method('reset');
        $translator->method('getCatalogue')->willReturnCallback(
            static fn (?string $locale): MessageCatalogue => new MessageCatalogue((string) $locale)
        );
        $translator->method('getSnippetSetId')->willReturn($this->snippetSetId);

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider->method('getLocaleForLanguageId')->willReturn('pl-PL');

        $loader = new ChannelSnippetLoader(
            $translator,
            $languageLocaleProvider,
            $this->createLanguageRepository()
        );

        $loader->load([], [], $this->channelContext);
    }

    public function testThrowsWhenLanguageIsNotAssignedToTheChannel(): void
    {
        $languageId = Uuid::randomHex();

        // the sales channel has no languages assigned
        $loader = $this->createLoader([], languageRepository: $this->createLanguageRepository([[]]));

        $this->expectExceptionObject(
            SnippetException::languageNotAvailableInChannel($languageId, $this->channelContext->getChannelId())
        );

        $loader->load([$languageId], [], $this->channelContext);
    }

    public function testThrowsOnMalformedLanguageId(): void
    {
        $loader = $this->createLoader([]);

        $this->expectExceptionObject(
            SnippetException::languageNotAvailableInChannel('not-a-uuid', $this->channelContext->getChannelId())
        );

        $loader->load(['not-a-uuid'], [], $this->channelContext);
    }

    /**
     * @param array<string, MessageCatalogue> $catalogues locale => catalogue served by the translator
     * @param array<string, string>|null $locales languageId => locale, defaults to the context language mapping to pl-PL
     * @param ChannelRepository<LanguageCollection>|null $languageRepository
     */
    private function createLoader(array $catalogues, ?array $locales = null, ?ChannelRepository $languageRepository = null): ChannelSnippetLoader
    {
        $locales ??= [$this->channelContext->getLanguageId() => 'pl-PL'];

        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('getCatalogue')->willReturnCallback(
            static fn (?string $locale): MessageCatalogue => $catalogues[(string) $locale] ?? new MessageCatalogue((string) $locale)
        );
        $translator->method('getSnippetSetId')->willReturn($this->snippetSetId);

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider->method('getLocaleForLanguageId')->willReturnCallback(
            static fn (string $languageId): string => $locales[$languageId] ?? 'en-GB'
        );

        return new ChannelSnippetLoader(
            $translator,
            $languageLocaleProvider,
            $languageRepository ?? $this->createLanguageRepository()
        );
    }

    /**
     * @param list<list<string>> $idSearches results of consecutive searchIds() calls, each a flat list of found language ids
     *
     * @return ChannelRepository<LanguageCollection>
     */
    private function createLanguageRepository(array $idSearches = []): ChannelRepository
    {
        /** @var StaticChannelRepository<LanguageCollection> $repository */
        $repository = new StaticChannelRepository($idSearches);

        return $repository;
    }
}
