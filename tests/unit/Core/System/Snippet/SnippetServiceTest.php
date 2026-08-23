<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet;

use Doctrine\DBAL\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Contena\Core\System\Snippet\DataTransfer\Language\Language as LanguageDto;
use Contena\Core\System\Snippet\DataTransfer\Language\LanguageCollection as LanguageDtoCollection;
use Contena\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Contena\Core\System\Snippet\Event\SnippetsThemeResolveEvent;
use Contena\Core\System\Snippet\Files\RemoteSnippetFile;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\Filter\SnippetFilterFactory;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\SnippetCollection;
use Contena\Core\System\Snippet\SnippetException;
use Contena\Core\System\Snippet\SnippetService;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Tests\Unit\Administration\Snippet\SnippetFileTrait;
use Contena\Tests\Unit\Core\System\Snippet\Mock\MockSnippetFile;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @internal
 */
#[CoversClass(SnippetService::class)]
class SnippetServiceTest extends TestCase
{
    use SnippetFileTrait;

    private SnippetFileCollection $snippetCollection;

    private Connection&MockObject $connection;

    private Flysystem $flysystem;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->flysystem = new Flysystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $this->filesystem = new Filesystem();
        $this->snippetCollection = new SnippetFileCollection();
        $this->addThemes();
    }

    /**
     * @param \Throwable|array<string, string> $expected
     * @param array<string, string> $catalogueMessages
     * @param array<string, string> $databaseSnippets
     */
    #[DataProvider('getFrontendSnippetsDataProvider')]
    public function testGetFrontendSnippets(
        array|\Throwable $expected = [],
        false|string $catalogueLocale = 'en-GB',
        array $catalogueMessages = [],
        ?string $fallbackLocale = null,
        ?string $channelId = null,
        ?string $usedTheme = null,
        array $databaseSnippets = []
    ): void {
        if ($expected instanceof \Throwable) {
            $this->expectException($expected::class);
        }

        $this->connection->expects($this->once())->method('fetchOne')->willReturn($catalogueLocale);
        $dispatcher = new EventDispatcher();

        $currentThemeName = $usedTheme ?? 'Frontend';

        $dispatcher->addListener(SnippetsThemeResolveEvent::class, static function (SnippetsThemeResolveEvent $event) use ($currentThemeName): void {
            $activeThemeNames = ['Frontend', 'CtTheme'];
            $event->setUsedThemes(array_values(array_unique([$currentThemeName, 'Frontend'])));
            $event->setUnusedThemes(array_values(array_diff($activeThemeNames, [$currentThemeName])));
        });

        if ($databaseSnippets !== []) {
            $this->connection->expects($this->once())->method('fetchAllKeyValue')->willReturn($databaseSnippets);
        }

        $catalogue = new MessageCatalogue((string) $catalogueLocale, ['messages' => $catalogueMessages]);
        $snippetService = $this->createSnippetService($dispatcher);
        $snippets = $snippetService->getFrontendSnippets($catalogue, Uuid::randomHex(), $fallbackLocale, $channelId);

        static::assertEquals($expected, $snippets);
    }

    public function testFindSnippetSetIdWithChannelDomain(): void
    {
        $snippetSetIdWithChannelDomain = Uuid::randomHex();

        $this->connection->expects($this->once())->method('fetchOne')->willReturn($snippetSetIdWithChannelDomain);

        $snippetService = $this->createSnippetService();

        $snippetSetId = $snippetService->findSnippetSetId(Uuid::randomHex(), Uuid::randomHex(), 'en-GB');

        static::assertSame($snippetSetId, $snippetSetIdWithChannelDomain);
    }

    public function testDecodeRemoteSnippets(): void
    {
        $remoteSnippetFile = new RemoteSnippetFile(
            'test',
            '/translation/locale/es-ES/Platform/frontend.json',
            'es-ES',
            'Contena',
            false,
            'Frontend'
        );

        $this->snippetCollection->add($remoteSnippetFile);

        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            [],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['en-GB'],
        );

        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->flysystem, $loader);

        $this->connection->expects($this->once())
            ->method('fetchOne')->willReturn('es-ES');

        $snippetService = $this->createSnippetService();

        $catalogue = new MessageCatalogue('es', ['messages' => []]);
        $snippets = $snippetService->getFrontendSnippets($catalogue, Uuid::randomHex(), 'es-ES', Uuid::randomHex());

        static::assertSame(['contena_frontend' => 'Platform frontend'], $snippets);
    }

    /**
     * @param array<string, string> $sets
     */
    #[DataProvider('findSnippetSetIdDataProvider')]
    public function testFindSnippetSetIdWithoutChannelDomain(array $sets, string $expected): void
    {
        $this->connection->expects($this->once())->method('fetchOne')->willReturn(null);
        $this->connection->expects($this->once())->method('fetchAllKeyValue')->willReturn($sets);

        $snippetService = $this->createSnippetService();

        $snippetSetId = $snippetService->findSnippetSetId(Uuid::randomHex(), Uuid::randomHex(), 'vi-VN');

        static::assertSame($snippetSetId, $expected);
    }

    public static function findSnippetSetIdDataProvider(): \Generator
    {
        $snippetSetIdWithVI = Uuid::randomHex();
        $snippetSetIdWithEN = Uuid::randomHex();

        yield 'get snippet set with locale vi-VN' => [
            'sets' => [
                'vi-VN' => $snippetSetIdWithVI,
                'en-GB' => $snippetSetIdWithEN,
            ],
            'expected' => $snippetSetIdWithVI,
        ];

        yield 'get snippet set without locale vi-VN' => [
            'sets' => [
                'en-GB' => $snippetSetIdWithEN,
            ],
            'expected' => $snippetSetIdWithEN,
        ];
    }

    public static function getFrontendSnippetsDataProvider(): \Generator
    {
        yield 'with unknown snippet id' => [
            'expected' => SnippetException::snippetSetNotFound('test'),
            'catalogueLocale' => false,
            'catalogueMessages' => [],
            'fallbackLocale' => null,
            'channelId' => null,
        ];

        yield 'with messages from catalogue' => [
            'expected' => [
                'catalogue_key' => 'Catalogue EN',
            ],
            'catalogueLocale' => 'en-GB',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue EN',
            ],
        ];

        yield 'fallback snippets are used if no localized snippet found' => [
            'expected' => [
                'title' => 'Frontend EN',
            ],
            'catalogueLocale' => 'vi',
            'catalogueMessages' => [],
            'fallbackLocale' => 'en',
        ];

        yield 'fallback snippets are overridden by catalogue messages' => [
            'expected' => [
                'catalogue_key' => 'Catalogue VI',
                'title' => 'Catalogue title VI',
            ],
            'catalogueLocale' => 'vi',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue VI',
                'title' => 'Catalogue title VI',
            ],
            'fallbackLocale' => 'en',
        ];

        yield 'fallback snippets, catalogue messages are overridden by localized snippets' => [
            'expected' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Frontend DE',
            ],
            'catalogueLocale' => 'de',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Catalogue title DE',
            ],
            'fallbackLocale' => 'en',
        ];

        yield 'fallback snippets, catalogue message, localized snippets are overridden by database snippets' => [
            'expected' => [
                'title' => 'Database title',
                'catalogue_key' => 'Catalogue DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Catalogue title',
            ],
            'fallbackLocale' => 'de',
            'channelId' => null,
            'usedTheme' => null,
            'databaseSnippets' => [
                'title' => 'Database title',
            ],
        ];

        yield 'with channel id without theme' => [
            'expected' => [
                'title' => 'Frontend DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [],
            'fallbackLocale' => 'de',
            'channelId' => Uuid::randomHex(),
            'usedTheme' => null,
            'databaseSnippets' => [],
        ];

        yield 'with channel id and theme' => [
            'expected' => [
                'title' => 'CtTheme DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [],
            'fallbackLocale' => 'de',
            'channelId' => Uuid::randomHex(),
            'usedTheme' => 'CtTheme',
        ];

        yield 'theme snippets are overridden by database snippets' => [
            'expected' => [
                'title' => 'Database title',
                'catalogue_key' => 'Catalogue DE',
            ],
            'catalogueLocale' => 'de-DE',
            'catalogueMessages' => [
                'catalogue_key' => 'Catalogue DE',
                'title' => 'Catalogue title',
            ],
            'fallbackLocale' => 'de',
            'channelId' => Uuid::randomHex(),
            'usedTheme' => 'CtTheme',
            'databaseSnippets' => [
                'title' => 'Database title',
            ],
        ];
    }

    /**
     * @param array<string,string> $expectedSnippets
     */
    #[DataProvider('getListDataProvider')]
    public function testGetList(string $iso, array $expectedSnippets): void
    {
        $availableFixtures = [
            'agnostic.es',
            'country.es-AR',
            'country.fr-CA',
            'agnostic.zh',
            'country.zh-Hans-CN',
        ];

        $baseIso = \explode('-', $iso, 2)[0];

        $baseFileName = 'agnostic.' . $baseIso;
        $countryFileName = 'country.' . $iso;

        $snippetCollection = new SnippetFileCollection();
        // only add files that exist in fixtures list
        if (\in_array($baseFileName, $availableFixtures, true)) {
            $snippetCollection->add(new MockSnippetFile($baseFileName, $baseIso));
        }

        if (\in_array($countryFileName, $availableFixtures, true)) {
            $snippetCollection->add(new MockSnippetFile($countryFileName, $iso));
        }

        // Create snippet set entity representing available snippet set
        $snippetSet = new SnippetSetEntity();
        $setId = Uuid::randomHex();
        $snippetSet->setId($setId);
        $snippetSet->setIso($iso);
        $snippetSet->setName('test');
        $snippetSet->setBaseFile($countryFileName . '.json');

        $snippetSetCollection = new SnippetSetCollection();
        $snippetSetCollection->add($snippetSet);

        $snippetSetRepository = StaticEntityRepository::of(SnippetSetCollection::class, [
            static function ($criteria, $context) use ($snippetSetCollection) {
                return $snippetSetCollection;
            },
        ]);
        $snippetRepository = StaticEntityRepository::of(SnippetCollection::class, [
            static function ($criteria, $context) {
                return new SnippetCollection();
            },
        ]);

        $service = $this->createSnippetService(
            snippetRepository: $snippetRepository,
            snippetSetRepository: $snippetSetRepository,
            snippetFileCollection: $snippetCollection,
            connection: $this->connection,
        );

        $context = Context::createDefaultContext();
        $result = $service->getList(1, 10, $context, [], []);

        // Assert the total count matches the number of expected translation keys
        static::assertSame(\count($expectedSnippets), $result['total']);

        // Assert each expected translation key is present and has the correct value
        foreach ($expectedSnippets as $translationKey => $value) {
            static::assertArrayHasKey($translationKey, $result['data']);
            static::assertSame($value, $result['data'][$translationKey][0]['value']);
        }
    }

    /**
     * Data provider for getList tests.
     */
    public static function getListDataProvider(): \Generator
    {
        yield 'agnostic locale es without country' => [
            'iso' => 'es',
            'expectedSnippets' => [
                'title' => 'Agnostic ES',
                'baseOnly' => 'Agnostic ES',
            ],
        ];

        yield 'es-AR iso loads exact locale and bare language as base' => [
            'iso' => 'es-AR',
            'expectedSnippets' => [
                'title' => 'Country es-AR',
                'baseOnly' => 'Agnostic ES',
            ],
        ];

        yield 'country exists without base' => [
            'iso' => 'fr-CA',
            'expectedSnippets' => [
                'title' => 'Country fr-CA',
            ],
        ];

        yield 'unknown regional variant falls back to agnostic language' => [
            'iso' => 'es-EM',
            'expectedSnippets' => [
                'title' => 'Agnostic ES',
                'baseOnly' => 'Agnostic ES',
            ],
        ];
    }

    public function testGetFrontendSnippetsUsesRegionalFallbackForExtensionSnippets(): void
    {
        // Extension only provides de-DE snippets, not de-AT
        $this->snippetCollection->add(new MockSnippetFile('extension.de-DE', 'de-DE'));

        $this->connection->expects($this->once())->method('fetchOne')->willReturn('de-AT');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SnippetsThemeResolveEvent::class, static function (SnippetsThemeResolveEvent $event): void {
            $event->setUsedThemes(['Frontend']);
            $event->setUnusedThemes([]);
        });

        $catalogue = new MessageCatalogue('de-AT', []);
        $snippetService = $this->createSnippetService($dispatcher);
        $snippets = $snippetService->getFrontendSnippets($catalogue, Uuid::randomHex(), null, null);

        static::assertArrayHasKey('extension.button', $snippets);
        static::assertSame('Jetzt kaufen', $snippets['extension.button']);
    }

    public function testGetListUsesRegionalFallbackForExtensionSnippets(): void
    {
        // Extension only provides de-DE snippets, not de-AT
        $snippetCollection = new SnippetFileCollection();
        $snippetCollection->add(new MockSnippetFile('extension.de-DE', 'de-DE'));

        $snippetSet = new SnippetSetEntity();
        $snippetSet->setId(Uuid::randomHex());
        $snippetSet->setIso('de-AT');
        $snippetSet->setName('Deutsch (Österreich)');
        $snippetSet->setBaseFile('extension.de-DE.json');

        $snippetSetCollection = new SnippetSetCollection();
        $snippetSetCollection->add($snippetSet);

        $snippetSetRepository = StaticEntityRepository::of(SnippetSetCollection::class, [$snippetSetCollection]);
        $snippetRepository = StaticEntityRepository::of(SnippetCollection::class, [new SnippetCollection()]);

        $service = $this->createSnippetService(
            snippetRepository: $snippetRepository,
            snippetSetRepository: $snippetSetRepository,
            snippetFileCollection: $snippetCollection,
            connection: $this->connection,
        );

        $result = $service->getList(1, 10, Context::createDefaultContext(), [], []);

        static::assertSame(1, $result['total']);
        static::assertArrayHasKey('extension.button', $result['data']);
        static::assertSame('Jetzt kaufen', $result['data']['extension.button'][0]['value']);
    }

    private function addThemes(): void
    {
        $this->snippetCollection->add(new MockSnippetFile('frontend.de', 'de', '{}', true, 'Frontend'));
        $this->snippetCollection->add(new MockSnippetFile('frontend.en', 'en', '{}', true, 'Frontend'));
        $this->snippetCollection->add(new MockSnippetFile('cttheme.de', 'de', '{}', true, 'CtTheme'));
        $this->snippetCollection->add(new MockSnippetFile('cttheme.en', 'en', '{}', true, 'CtTheme'));
    }

    /**
     * All parameters are optional. When provided they override defaults.
     *
     * @param StaticEntityRepository<SnippetCollection>|null $snippetRepository
     * @param StaticEntityRepository<SnippetSetCollection>|null $snippetSetRepository
     */
    private function createSnippetService(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?EntityRepository $snippetRepository = null,
        ?EntityRepository $snippetSetRepository = null,
        ?SnippetFileCollection $snippetFileCollection = null,
        ?Connection $connection = null,
        ?SnippetFilterFactory $snippetFilterFactory = null,
        ?ExtensionDispatcher $extensionDispatcher = null
    ): SnippetService {
        if ($snippetRepository === null) {
            $snippetRepository = StaticEntityRepository::of(SnippetCollection::class);
        }

        if ($snippetSetRepository === null) {
            $snippetSetRepository = StaticEntityRepository::of(SnippetSetCollection::class);
        }

        $snippetFileCollection = $snippetFileCollection ?? $this->snippetCollection;
        $connection = $connection ?? $this->connection;
        $snippetFilterFactory = $snippetFilterFactory ?? static::createStub(SnippetFilterFactory::class);
        $extensionDispatcher = $extensionDispatcher ?? new ExtensionDispatcher(new EventDispatcher());

        /** @var EntityRepository<SnippetCollection> $snippetRepository */
        /** @var EntityRepository<SnippetSetCollection> $snippetSetRepository */
        return new SnippetService(
            $connection,
            $snippetFileCollection,
            $snippetRepository,
            $snippetSetRepository,
            $snippetFilterFactory,
            $extensionDispatcher,
            $eventDispatcher ?? new EventDispatcher(),
            $this->flysystem,
            $this->filesystem,
        );
    }

    private function getTranslationLoader(TranslationConfig $config): TranslationLoader
    {
        $languageRepository = StaticEntityRepository::of(LanguageCollection::class);

        $localeRepository = StaticEntityRepository::of(LocaleCollection::class);

        $snippetSetRepository = StaticEntityRepository::of(SnippetSetCollection::class);

        return new TranslationLoader(
            translationWriter: $this->flysystem,
            languageRepository: $languageRepository,
            localeRepository: $localeRepository,
            snippetSetRepository: $snippetSetRepository,
            client: static::createStub(ClientInterface::class),
            config: $config,
            eventDispatcher: new EventDispatcher(),
        );
    }
}
