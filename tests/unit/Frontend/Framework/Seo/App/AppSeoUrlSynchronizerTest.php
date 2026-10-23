<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\Feature\AppFeature;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlConfig;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlSynchronizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(AppSeoUrlSynchronizer::class)]
class AppSeoUrlSynchronizerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_APP_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const IMPRINT_ROUTE = 'frontend.app.SwagSeoUrlApp.imprint';

    private const CONTACT_ROUTE = 'frontend.app.SwagSeoUrlApp.contact';

    private const CHANNEL_ID = 'cccccccccccccccccccccccccccccccc';

    private const OTHER_CHANNEL_ID = 'dddddddddddddddddddddddddddddddd';

    private const CHANNEL_WITHOUT_DOMAINS_ID = '33333333333333333333333333333333';

    private const GERMAN_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private const SWISS_GERMAN_ID = 'ffffffffffffffffffffffffffffffff';

    private const FRENCH_ID = '22222222222222222222222222222222';

    private const LOCALES = [
        Defaults::LANGUAGE_SYSTEM => 'en-GB',
        self::GERMAN_ID => 'de-DE',
        self::SWISS_GERMAN_ID => 'de-CH',
        self::FRENCH_ID => 'fr-FR',
    ];

    /**
     * @var list<array{context: Context, routeName: string, foreignKeys: list<string>, seoUrls: list<array<string, mixed>>, channel: ChannelEntity}>
     */
    private array $written = [];

    private SeoUrlPersister $seoUrlPersister;

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->written = [];
        $this->requestStack = new RequestStack();

        $seoUrlPersister = static::createStub(SeoUrlPersister::class);
        $seoUrlPersister->method('forceUpdateSeoUrls')->willReturnCallback(
            function (Context $context, string $routeName, array $foreignKeys, iterable $seoUrls, ChannelEntity $channel): void {
                $this->written[] = [
                    'context' => $context,
                    'routeName' => $routeName,
                    'foreignKeys' => array_values($foreignKeys),
                    'seoUrls' => iterator_to_array($seoUrls, false),
                    'channel' => $channel,
                ];
            }
        );
        $this->seoUrlPersister = $seoUrlPersister;

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider->method('getLocaleForLanguageId')->willReturnCallback(
            static fn (string $languageId): string => self::LOCALES[$languageId] ?? static::fail('Unknown language ' . $languageId)
        );
        $this->languageLocaleProvider = $languageLocaleProvider;
    }

    public function testEveryDomainLanguageGetsACanonicalModifiedSeoUrl(): void
    {
        $channel = $this->channel(self::CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM), $this->domain(self::GERMAN_ID));

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint', 'de-DE' => 'impressum']))],
            $this->channelRepository($channel)
        )->syncStaticRoutes();

        static::assertCount(2, $this->written);

        static::assertSame(self::IMPRINT_ROUTE, $this->written[0]['routeName']);
        static::assertSame([Uuid::fromStringToHex(self::IMPRINT_ROUTE)], $this->written[0]['foreignKeys']);
        static::assertSame($channel, $this->written[0]['channel']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->written[0]['context']->getLanguageId());
        static::assertSame([[
            'foreignKey' => Uuid::fromStringToHex(self::IMPRINT_ROUTE),
            'pathInfo' => '/frontend/script/imprint',
            'seoPathInfo' => 'imprint',
            'channelId' => self::CHANNEL_ID,
            'isCanonical' => true,
            'isModified' => true,
            'isDeleted' => false,
        ]], $this->written[0]['seoUrls']);

        static::assertSame(self::GERMAN_ID, $this->written[1]['context']->getLanguageId());
        static::assertSame('impressum', $this->written[1]['seoUrls'][0]['seoPathInfo']);
    }

    /**
     * @param list<string> $expectedLanguageChain
     */
    #[DataProvider('domainLanguages')]
    public function testTheSeoUrlIsWrittenWithTheDomainLanguageFallingBackToTheSystemLanguage(string $languageId, array $expectedLanguageChain): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->channelRepository($this->channel(self::CHANNEL_ID, $this->domain($languageId)))
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame($expectedLanguageChain, $this->written[0]['context']->getLanguageIdChain());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function domainLanguages(): iterable
    {
        yield 'a domain language other than the system language falls back to it' => [
            self::GERMAN_ID,
            [self::GERMAN_ID, Defaults::LANGUAGE_SYSTEM],
        ];
        yield 'the system language is not repeated' => [
            Defaults::LANGUAGE_SYSTEM,
            [Defaults::LANGUAGE_SYSTEM],
        ];
    }

    /**
     * @param array<string, string> $paths
     */
    #[DataProvider('pathsByLocale')]
    public function testThePathOfTheDomainLanguageIsUsedWithTheSystemLanguageAsFallback(string $languageId, array $paths, string $expectedPath): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', $paths))],
            $this->channelRepository($this->channel(self::CHANNEL_ID, $this->domain($languageId)))
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame($expectedPath, $this->written[0]['seoUrls'][0]['seoPathInfo']);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function pathsByLocale(): iterable
    {
        yield 'the path of the domain language beats the path of the system language' => [
            self::GERMAN_ID,
            ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
            'impressum',
        ];
        yield 'the path of the domain language beats the order of the declaration' => [
            self::SWISS_GERMAN_ID,
            ['de-DE' => 'impressum', 'de-CH' => 'impressum-schweiz'],
            'impressum-schweiz',
        ];
        yield 'a regional language without a path of its own uses the path of the system language' => [
            self::SWISS_GERMAN_ID,
            ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
            'imprint',
        ];
        yield 'a language without a path uses the path of the system language' => [
            self::FRENCH_ID,
            ['de-DE' => 'impressum', 'en-GB' => 'imprint'],
            'imprint',
        ];
        yield 'without a path for the domain or the system language the first declared path is used' => [
            self::FRENCH_ID,
            ['de-DE' => 'impressum', 'nl-NL' => 'colofon'],
            'impressum',
        ];
    }

    public function testDomainsSharingALanguageAreWrittenOnce(): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->channelRepository($this->channel(self::CHANNEL_ID, $this->domain(self::GERMAN_ID), $this->domain(self::GERMAN_ID)))
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
    }

    public function testEveryStaticSeoUrlIsWrittenForEveryChannel(): void
    {
        $this->synchronizer(
            [
                $this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint'])),
                $this->feature($this->seoUrl('contact', ['en-GB' => 'contact'])),
            ],
            $this->channelRepository(
                $this->channel(self::CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)),
                $this->channel(self::OTHER_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)),
            )
        )->syncStaticRoutes();

        static::assertSame(
            [
                [self::CHANNEL_ID, self::IMPRINT_ROUTE, 'imprint'],
                [self::CHANNEL_ID, self::CONTACT_ROUTE, 'contact'],
                [self::OTHER_CHANNEL_ID, self::IMPRINT_ROUTE, 'imprint'],
                [self::OTHER_CHANNEL_ID, self::CONTACT_ROUTE, 'contact'],
            ],
            array_map(
                static fn (array $write): array => [$write['channel']->getId(), $write['routeName'], $write['seoUrls'][0]['seoPathInfo']],
                $this->written
            )
        );
    }

    public function testChannelsWithoutDomainsGetNoSeoUrls(): void
    {
        $withoutLoadedDomains = new ChannelEntity();
        $withoutLoadedDomains->setId(self::OTHER_CHANNEL_ID);

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->channelRepository(
                $withoutLoadedDomains,
                $this->channel(self::CHANNEL_WITHOUT_DOMAINS_ID),
                $this->channel(self::CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)),
            )
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame(self::CHANNEL_ID, $this->written[0]['channel']->getId());
    }

    /**
     * @param list<string> $expectedRouteNames
     */
    #[DataProvider('appFilters')]
    public function testTheSyncCanBeLimitedToOneApp(?string $appId, array $expectedRouteNames): void
    {
        $this->synchronizer(
            [
                $this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']), appId: self::APP_ID),
                $this->feature($this->seoUrl('contact', ['en-GB' => 'contact']), appId: self::OTHER_APP_ID),
            ],
            $this->channelRepository($this->channel(self::CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)))
        )->syncStaticRoutes($appId);

        static::assertSame($expectedRouteNames, array_column($this->written, 'routeName'));
    }

    /**
     * @return iterable<string, array{?string, list<string>}>
     */
    public static function appFilters(): iterable
    {
        yield 'without an app the static SEO URLs of all active apps are written' => [null, [self::IMPRINT_ROUTE, self::CONTACT_ROUTE]];
        yield 'with an app only its static SEO URLs are written' => [self::OTHER_APP_ID, [self::CONTACT_ROUTE]];
    }

    public function testWithoutStaticSeoUrlsOfActiveAppsNoChannelIsLoaded(): void
    {
        $this->synchronizer([], $this->channelRepositoryThatMustNotBeSearched())->syncStaticRoutes();

        static::assertSame([], $this->written);
    }

    public function testWithoutStaticSeoUrlsOfTheGivenAppNoChannelIsLoaded(): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('contact', ['en-GB' => 'contact']), appId: self::OTHER_APP_ID)],
            $this->channelRepositoryThatMustNotBeSearched()
        )->syncStaticRoutes(self::APP_ID);

        static::assertSame([], $this->written);
    }

    public function testOnlyActiveChannelsThatAreNotHeadlessAreLoadedWithTheirDomains(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [
            static function (Criteria $criteria): ChannelCollection {
                static::assertEquals(
                    [
                        new EqualsFilter('active', true),
                        new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_API)]),
                    ],
                    $criteria->getFilters()
                );
                static::assertTrue($criteria->hasAssociation('domains'));

                return new ChannelCollection();
            },
        ]);

        $this->synchronizer([$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))], $channelRepository)->syncStaticRoutes();

        static::assertSame([], $channelRepository->searches);
    }

    public function testThePathInfoPointsToTheScriptEndpointOfTheHook(): void
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static function (string $name, array $parameters): string {
            static::assertSame('frontend.script_endpoint', $name);
            static::assertSame(['hook' => 'legal-notice'], $parameters);

            return '/frontend/script/legal-notice';
        });

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint'], hook: 'legal-notice'))],
            $this->channelRepository($this->channel(self::CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM))),
            $router
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame('/frontend/script/legal-notice', $this->written[0]['seoUrls'][0]['pathInfo']);
    }

    #[DataProvider('basePaths')]
    public function testTheBasePathOfTheMainRequestIsNotPartOfThePathInfo(?string $basePath, string $generatedPath, string $expectedPathInfo): void
    {
        if ($basePath !== null) {
            $this->requestStack->push(Request::create(
                $basePath . '/admin',
                server: ['SCRIPT_FILENAME' => '/var/www/html' . $basePath . '/index.php', 'SCRIPT_NAME' => $basePath . '/index.php']
            ));
        }

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn($generatedPath);

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->channelRepository($this->channel(self::CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM))),
            $router
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame($expectedPathInfo, $this->written[0]['seoUrls'][0]['pathInfo']);
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function basePaths(): iterable
    {
        yield 'without a main request the generated path is used' => [null, '/frontend/script/imprint', '/frontend/script/imprint'];
        yield 'the base path of the main request is stripped' => ['/shop', '/shop/frontend/script/imprint', '/frontend/script/imprint'];
        yield 'a generated path outside of the base path is used as it is' => ['/shop', '/frontend/script/imprint', '/frontend/script/imprint'];
    }

    /**
     * @param list<AppFeature<AppSeoUrlConfig>> $features
     * @param StaticEntityRepository<ChannelCollection> $channelRepository
     */
    private function synchronizer(array $features, StaticEntityRepository $channelRepository, ?RouterInterface $router = null): AppSeoUrlSynchronizer
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturnCallback(static function (string $featureClass) use ($features): array {
            static::assertSame(AppSeoUrlConfig::class, $featureClass);

            return $features;
        });

        if ($router === null) {
            $router = static::createStub(RouterInterface::class);
            $router->method('generate')->willReturnCallback(
                static fn (string $name, array $parameters): string => '/frontend/script/' . $parameters['hook']
            );
        }

        return new AppSeoUrlSynchronizer(
            $storage,
            $channelRepository,
            $this->seoUrlPersister,
            $this->languageLocaleProvider,
            $router,
            $this->requestStack,
        );
    }

    /**
     * @return StaticEntityRepository<ChannelCollection>
     */
    private function channelRepository(ChannelEntity ...$channels): StaticEntityRepository
    {
        return new StaticEntityRepository([new ChannelCollection($channels)]);
    }

    /**
     * @return StaticEntityRepository<ChannelCollection>
     */
    private function channelRepositoryThatMustNotBeSearched(): StaticEntityRepository
    {
        return StaticEntityRepository::of(ChannelCollection::class, [
            static fn (): ChannelCollection => static::fail('No sales channel must be loaded without static SEO URLs to write'),
        ]);
    }

    /**
     * @return AppFeature<AppSeoUrlConfig>
     */
    private function feature(AppSeoUrlConfig $seoUrl, string $appId = self::APP_ID): AppFeature
    {
        return new AppFeature(
            appId: $appId,
            appName: 'SwagSeoUrlApp',
            appActive: true,
            appVersion: '1.0.0',
            appHasSecret: false,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            config: $seoUrl,
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private function seoUrl(string $name, array $paths, ?string $hook = null): AppSeoUrlConfig
    {
        return new AppSeoUrlConfig(
            name: $name,
            routeName: 'frontend.app.SwagSeoUrlApp.' . $name,
            hook: $hook ?? $name,
            paths: $paths,
        );
    }

    private function channel(string $id, ChannelDomainEntity ...$domains): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setId($id);
        $channel->setDomains(new ChannelDomainCollection($domains));

        return $channel;
    }

    private function domain(string $languageId): ChannelDomainEntity
    {
        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setLanguageId($languageId);

        return $domain;
    }
}
