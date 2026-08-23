<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigLoader;
use Contena\Frontend\Theme\Event\ThemeAssignedEvent;
use Contena\Frontend\Theme\Event\ThemeConfigChangedEvent;
use Contena\Frontend\Theme\Event\ThemeConfigResetEvent;
use Contena\Frontend\Theme\Exception\ThemeConfigException;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Message\CompileThemeMessage;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeMergedConfigBuilder;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;

/**
 * @internal
 */
#[CoversClass(ThemeService::class)]
class ThemeServiceTest extends TestCase
{
    private Connection&Stub $connectionMock;

    private FrontendPluginRegistry&Stub $storefrontPluginRegistryMock;

    /**
     * @var EntityRepository<ThemeCollection>&Stub
     */
    private EntityRepository&Stub $themeRepositoryMock;

    /**
     * @var EntityRepository<EntityCollection<Entity>>&Stub
     */
    private EntityRepository&Stub $themeChannelRepositoryMock;

    private ThemeCompiler&Stub $themeCompilerMock;

    private EventDispatcher&Stub $eventDispatcherMock;

    private ThemeMergedConfigBuilder&Stub $mergedConfigBuilderMock;

    private DatabaseConfigLoader&Stub $databaseConfigLoaderMock;

    private ThemeRuntimeConfigService&Stub $runtimeConfigServiceMock;

    private Context $context;

    private SystemConfigService&Stub $systemConfigMock;

    private MessageBus&Stub $messageBusMock;

    private ScssPhpCompiler&Stub $scssCompilerMock;

    protected function setUp(): void
    {
        $this->connectionMock = static::createStub(Connection::class);
        $this->storefrontPluginRegistryMock = static::createStub(FrontendPluginRegistry::class);
        $this->themeRepositoryMock = static::createStub(EntityRepository::class);
        $this->themeChannelRepositoryMock = static::createStub(EntityRepository::class);
        $this->themeCompilerMock = static::createStub(ThemeCompiler::class);
        $this->eventDispatcherMock = static::createStub(EventDispatcher::class);
        $this->databaseConfigLoaderMock = static::createStub(DatabaseConfigLoader::class);
        $this->context = Context::createDefaultContext();
        $this->systemConfigMock = static::createStub(SystemConfigService::class);
        $this->messageBusMock = static::createStub(MessageBus::class);
        $this->mergedConfigBuilderMock = static::createStub(ThemeMergedConfigBuilder::class);
        $this->scssCompilerMock = static::createStub(ScssPhpCompiler::class);
        $this->runtimeConfigServiceMock = static::createStub(ThemeRuntimeConfigService::class);
    }

    public function testAssignTheme(): void
    {
        $themeId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('transactional')->willReturnCallback(static function (callable $callback): void {
            $callback();
        });

        $themeChannelRepository = $this->createMock(EntityRepository::class);
        $themeChannelRepository->expects($this->once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'channelId' => TestDefaults::CHANNEL,
            ]],
            $this->context
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::CHANNEL, $this->context)
        );

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->once())->method('compileTheme')->with(
            TestDefaults::CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $themeService = $this->getThemeService(
            themeChannelRepository: $themeChannelRepository,
            themeCompiler: $themeCompiler,
            eventDispatcher: $eventDispatcher,
            connection: $connection,
        );

        $assigned = $themeService->assignTheme($themeId, TestDefaults::CHANNEL, $this->context);

        static::assertTrue($assigned);
    }

    public function testAssignThemeRecordsThePendingThemeOnTheDeferredPath(): void
    {
        // the pending theme drives both the supersede check and the admin indicator
        $themeId = Uuid::randomHex();

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn(true); // async compilation enabled
        $systemConfig->expects($this->once())->method('set')->with(
            ThemeService::CONFIG_KEY_PENDING_THEME,
            $themeId,
            TestDefaults::CHANNEL,
            false,
        );

        $messageBus = static::createStub(MessageBus::class);
        $messageBus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $this->context->addState(ThemeService::STATE_DEFER_ASSIGNMENT);

        $this->getThemeService(systemConfig: $systemConfig, messageBus: $messageBus)
            ->assignTheme($themeId, TestDefaults::CHANNEL, $this->context);
    }

    public function testAssignThemeRestoresThePendingMarkerWhenDispatchFails(): void
    {
        // if the compile message cannot be queued, the marker is rolled back to its previous value
        // so the admin does not keep polling a switch that was never started
        $themeId = Uuid::randomHex();
        $previousThemeId = Uuid::randomHex();

        $systemConfig = new StaticSystemConfigService([
            ThemeService::CONFIG_THEME_COMPILE_ASYNC => true,
            TestDefaults::CHANNEL => [ThemeService::CONFIG_KEY_PENDING_THEME => $previousThemeId],
        ]);

        $messageBus = static::createStub(MessageBus::class);
        $messageBus->method('dispatch')->willThrowException(new \RuntimeException('transport down'));

        $this->context->addState(ThemeService::STATE_DEFER_ASSIGNMENT);

        try {
            $this->getThemeService(systemConfig: $systemConfig, messageBus: $messageBus)
                ->assignTheme($themeId, TestDefaults::CHANNEL, $this->context);
            static::fail('dispatch failure should propagate');
        } catch (\RuntimeException $e) {
            static::assertSame('transport down', $e->getMessage());
        }

        // the marker is restored to the previously pending theme, not left pointing at the failed one
        static::assertSame(
            $previousThemeId,
            $systemConfig->getString(ThemeService::CONFIG_KEY_PENDING_THEME, TestDefaults::CHANNEL)
        );
    }

    public function testAssignThemeRecordsThePendingThemeOnTheSynchronousPath(): void
    {
        // the marker is written for every switch, not just deferred ones, so a synchronous switch
        // supersedes an in-flight deferred compile and leaves no phantom pending indicator
        $themeId = Uuid::randomHex();

        $systemConfig = new StaticSystemConfigService();

        $this->getThemeService(systemConfig: $systemConfig)
            ->assignTheme($themeId, TestDefaults::CHANNEL, $this->context);

        static::assertSame(
            $themeId,
            $systemConfig->getString(ThemeService::CONFIG_KEY_PENDING_THEME, TestDefaults::CHANNEL)
        );
    }

    public function testAssignThemeSkipCompile(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('transactional')->willReturnCallback(static function (callable $callback): void {
            $callback();
        });

        $themeId = Uuid::randomHex();

        $themeChannelRepository = $this->createMock(EntityRepository::class);
        $themeChannelRepository->expects($this->once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'channelId' => TestDefaults::CHANNEL,
            ]],
            $this->context
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::CHANNEL, $this->context)
        );

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->never())->method('compileTheme');

        $themeService = $this->getThemeService(
            themeChannelRepository: $themeChannelRepository,
            themeCompiler: $themeCompiler,
            eventDispatcher: $eventDispatcher,
            connection: $connection,
        );

        $assigned = $themeService->assignTheme($themeId, TestDefaults::CHANNEL, $this->context, true);

        static::assertTrue($assigned);
    }

    public function testAssignThemeDefersAssignmentWhenRequestedAndAsyncCompilationIsEnabled(): void
    {
        $themeId = Uuid::randomHex();

        // async on + deferral requested: nothing happens synchronously, only a compile
        // message carrying the assign flag is queued.
        $this->systemConfigMock->method('get')->willReturn(true);

        $themeChannelRepository = $this->createMock(EntityRepository::class);
        $themeChannelRepository->expects($this->never())->method('upsert');

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->never())->method('compileTheme');

        $dispatchedMessage = null;
        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $message;

                return new Envelope($message);
            });

        $themeService = $this->getThemeService(
            themeChannelRepository: $themeChannelRepository,
            themeCompiler: $themeCompiler,
            eventDispatcher: $eventDispatcher,
            messageBus: $messageBus,
        );

        $this->context->addState(ThemeService::STATE_DEFER_ASSIGNMENT);
        $assigned = $themeService->assignTheme($themeId, TestDefaults::CHANNEL, $this->context);

        static::assertTrue($assigned);
        static::assertInstanceOf(CompileThemeMessage::class, $dispatchedMessage);
        static::assertTrue($dispatchedMessage->isAssign());
        static::assertSame($themeId, $dispatchedMessage->getThemeId());
        static::assertSame(TestDefaults::CHANNEL, $dispatchedMessage->getChannelId());
    }

    public function testAssignThemeStaysSynchronousWithoutDeferralEvenWhenAsyncEnabled(): void
    {
        // BC guard: without deferral the relation is upserted synchronously even when async
        // is enabled, since callers like extension removal rely on it taking effect at once.
        $themeId = Uuid::randomHex();

        $this->systemConfigMock->method('get')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('transactional')->willReturnCallback(static function (callable $callback): void {
            $callback();
        });

        $themeChannelRepository = $this->createMock(EntityRepository::class);
        $themeChannelRepository->expects($this->once())->method('upsert')->with(
            [[
                'themeId' => $themeId,
                'channelId' => TestDefaults::CHANNEL,
            ]],
            $this->context
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            new ThemeAssignedEvent($themeId, TestDefaults::CHANNEL, $this->context)
        );

        // the compile itself is still queued (async is on); only the assignment stays synchronous
        $messageBus = static::createStub(MessageBus::class);
        $messageBus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $themeService = $this->getThemeService(
            themeChannelRepository: $themeChannelRepository,
            eventDispatcher: $eventDispatcher,
            connection: $connection,
            messageBus: $messageBus,
        );

        $assigned = $themeService->assignTheme($themeId, TestDefaults::CHANNEL, $this->context);

        static::assertTrue($assigned);
    }

    public function testCompileTheme(): void
    {
        $themeId = Uuid::randomHex();

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->once())->method('compileTheme')->with(
            TestDefaults::CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $this->getThemeService(themeCompiler: $themeCompiler)->compileTheme(TestDefaults::CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeRefreshesConfigValuesWithStaticFileConfigLoader(): void
    {
        $themeId = Uuid::randomHex();
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write(
            \sprintf('theme-config/%s.json', $themeId),
            json_encode([
                'styleFiles' => [],
                'scriptFiles' => [],
            ], \JSON_THROW_ON_ERROR)
        );
        $configLoader = new StaticFileConfigLoader($fs);

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->once())->method('compileTheme')->with(
            TestDefaults::CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );
        $themeCompiler->expects($this->never())->method('buildComponentImportMap');

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->expects($this->once())->method('refreshConfigValues')->with($themeId, $this->context);
        $runtimeConfigService->expects($this->never())->method('refreshRuntimeConfig');

        $this->getThemeService(
            themeCompiler: $themeCompiler,
            configLoader: $configLoader,
            runtimeConfigService: $runtimeConfigService,
        )->compileTheme(TestDefaults::CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeAsyncSkipHeader(): void
    {
        $themeId = Uuid::randomHex();

        $this->context->addState(ThemeService::STATE_NO_QUEUE);

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->never())->method('dispatch');

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->once())->method('compileTheme')->with(
            TestDefaults::CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $this->systemConfigMock->method('get')->willReturn(true);

        $this->getThemeService(themeCompiler: $themeCompiler, messageBus: $messageBus)->compileTheme(TestDefaults::CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeAsyncSetting(): void
    {
        $themeId = Uuid::randomHex();

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->never())->method('compileTheme');

        $context = $this->context;
        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->willReturnCallback(static function () use ($themeId, $context): Envelope {
                return new Envelope(
                    new CompileThemeMessage(
                        TestDefaults::CHANNEL,
                        $themeId,
                        true,
                        $context
                    )
                );
            });

        $this->systemConfigMock->method('get')->willReturn(true);

        $this->getThemeService(themeCompiler: $themeCompiler, messageBus: $messageBus)->compileTheme(TestDefaults::CHANNEL, $themeId, $this->context);
    }

    public function testCompileThemeGivenConf(): void
    {
        $themeId = Uuid::randomHex();

        $confCollection = new FrontendPluginConfigurationCollection();

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->once())->method('compileTheme')->with(
            TestDefaults::CHANNEL,
            $themeId,
            static::anything(),
            $confCollection,
            true,
            $this->context
        );

        $this->getThemeService(themeCompiler: $themeCompiler)->compileTheme(TestDefaults::CHANNEL, $themeId, $this->context, $confCollection);
    }

    public function testCompileThemeWithAssets(): void
    {
        $themeId = Uuid::randomHex();

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->once())->method('compileTheme')->with(
            TestDefaults::CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            false,
            $this->context
        );

        $this->getThemeService(themeCompiler: $themeCompiler)->compileTheme(TestDefaults::CHANNEL, $themeId, $this->context, null, false);
    }

    public function testRefreshThemeImportMap(): void
    {
        $themeId = Uuid::randomHex();
        $storefrontConfig = new FrontendPluginConfiguration('Frontend');
        $configurationCollection = new FrontendPluginConfigurationCollection();
        $importMap = ['imports' => ['contena' => '/theme/contena.js']];

        $databaseConfigLoader = $this->createMock(DatabaseConfigLoader::class);
        $databaseConfigLoader
            ->expects($this->once())
            ->method('load')
            ->with($themeId, $this->context)
            ->willReturn($storefrontConfig);

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler
            ->expects($this->once())
            ->method('buildComponentImportMap')
            ->with($configurationCollection)
            ->willReturn($importMap);

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService
            ->expects($this->once())
            ->method('refreshRuntimeConfig')
            ->with(
                $themeId,
                $storefrontConfig,
                $this->context,
                false,
                $configurationCollection,
                $importMap
            );

        $this->getThemeService(
            themeCompiler: $themeCompiler,
            configLoader: $databaseConfigLoader,
            runtimeConfigService: $runtimeConfigService,
        )->refreshThemeImportMap(
            TestDefaults::CHANNEL,
            $themeId,
            $this->context,
            $configurationCollection
        );
    }

    public function testCompileThemePassesEmptyImportMapWhenBuildReturnsNull(): void
    {
        $themeId = Uuid::randomHex();
        $storefrontConfig = new FrontendPluginConfiguration('Frontend');
        $configurationCollection = new FrontendPluginConfigurationCollection();

        $databaseConfigLoader = $this->createMock(DatabaseConfigLoader::class);
        $databaseConfigLoader
            ->expects($this->once())
            ->method('load')
            ->with($themeId, $this->context)
            ->willReturn($storefrontConfig);

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler
            ->expects($this->once())
            ->method('compileTheme')
            ->with(
                TestDefaults::CHANNEL,
                $themeId,
                $storefrontConfig,
                $configurationCollection,
                true,
                $this->context
            );

        $themeCompiler
            ->expects($this->once())
            ->method('buildComponentImportMap')
            ->with($configurationCollection)
            ->willReturn(null);

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService
            ->expects($this->once())
            ->method('refreshRuntimeConfig')
            ->with(
                $themeId,
                $storefrontConfig,
                $this->context,
                true,
                $configurationCollection,
                ['imports' => []]
            );

        $this->getThemeService(
            themeCompiler: $themeCompiler,
            configLoader: $databaseConfigLoader,
            runtimeConfigService: $runtimeConfigService,
        )->compileTheme(
            TestDefaults::CHANNEL,
            $themeId,
            $this->context,
            $configurationCollection
        );
    }

    public function testRefreshThemeImportMapPassesEmptyImportMapWhenBuildReturnsNull(): void
    {
        $themeId = Uuid::randomHex();
        $storefrontConfig = new FrontendPluginConfiguration('Frontend');
        $configurationCollection = new FrontendPluginConfigurationCollection();

        $databaseConfigLoader = $this->createMock(DatabaseConfigLoader::class);
        $databaseConfigLoader
            ->expects($this->once())
            ->method('load')
            ->with($themeId, $this->context)
            ->willReturn($storefrontConfig);

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler
            ->expects($this->once())
            ->method('buildComponentImportMap')
            ->with($configurationCollection)
            ->willReturn(null);

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService
            ->expects($this->once())
            ->method('refreshRuntimeConfig')
            ->with(
                $themeId,
                $storefrontConfig,
                $this->context,
                false,
                $configurationCollection,
                ['imports' => []]
            );

        $this->getThemeService(
            themeCompiler: $themeCompiler,
            configLoader: $databaseConfigLoader,
            runtimeConfigService: $runtimeConfigService,
        )->refreshThemeImportMap(
            TestDefaults::CHANNEL,
            $themeId,
            $this->context,
            $configurationCollection
        );
    }

    public function testRefreshThemeImportMapReturnsEarlyWithStaticFileConfigLoader(): void
    {
        $themeId = Uuid::randomHex();
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write(\sprintf('theme-config/%s.json', $themeId), json_encode([
            'styleFiles' => [],
            'scriptFiles' => [],
        ], \JSON_THROW_ON_ERROR));
        $configLoader = new StaticFileConfigLoader($fs);

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->never())->method('buildComponentImportMap');

        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService->expects($this->never())->method('refreshRuntimeConfig');

        $themeService = $this->getThemeService(
            themeCompiler: $themeCompiler,
            configLoader: $configLoader,
            runtimeConfigService: $runtimeConfigService,
        );

        $themeService->refreshThemeImportMap(
            TestDefaults::CHANNEL,
            $themeId,
            $this->context,
            new FrontendPluginConfigurationCollection()
        );
    }

    public function testCompileThemeById(): void
    {
        $themeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'channelId' => TestDefaults::CHANNEL,
                    'dependentId' => $dependentThemeId,
                    'dchannelId' => TestDefaults::CHANNEL,
                ],
            ]
        );

        $parameters = [];

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler
            ->expects($this->exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(static function ($channelId, $themeId) use (&$parameters): void {
                $parameters[] = [$channelId, $themeId];
            });

        $this->getThemeService(themeCompiler: $themeCompiler)->compileThemeById($themeId, $this->context);

        static::assertSame([
            [
                TestDefaults::CHANNEL,
                $themeId,
            ],
            [
                TestDefaults::CHANNEL,
                $dependentThemeId,
            ],
        ], $parameters);
    }

    public function testUpdateTheme(): void
    {
        $themeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'channelId' => TestDefaults::CHANNEL,
                    'dependentId' => $dependentThemeId,
                    'dchannelId' => TestDefaults::CHANNEL,
                ],
            ]
        );

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection(
                    [
                        new ThemeEntity()->assign(
                            [
                                '_uniqueIdentifier' => $themeId,
                                'channels' => new ChannelCollection(),
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        // Mock the getPlainThemeConfiguration method to return an empty configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->willReturn([
                'fields' => [],
            ]);

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->exactly(2))->method('compileTheme');

        $this->getThemeService(themeCompiler: $themeCompiler)->updateTheme($themeId, null, null, $this->context);
    }

    public function testUpdateThemeWithConfig(): void
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'channelId' => TestDefaults::CHANNEL,
                    'dependentId' => $dependentThemeId,
                    'dchannelId' => TestDefaults::CHANNEL,
                ],
            ]
        );

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection(
                    [
                        new ThemeEntity()->assign(
                            [
                                '_uniqueIdentifier' => $themeId,
                                'channels' => new ChannelCollection(),
                                'configValues' => [
                                    'test' => ['value' => ['no_test']],
                                ],
                                'baseConfig' => [
                                    'fields' => [
                                        'test' => [
                                            'type' => 'string',
                                            'value' => 'test',
                                        ],
                                    ],
                                ],
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        // Mock the getPlainThemeConfiguration method to return the expected configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->willReturn([
                'fields' => [
                    'test' => [
                        'type' => 'string',
                        'value' => 'test',
                    ],
                ],
            ]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            new ThemeConfigChangedEvent($themeId, ['test' => ['value' => ['test']]], $this->context)
        );

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->exactly(2))->method('compileTheme');

        $this->getThemeService(themeCompiler: $themeCompiler, eventDispatcher: $eventDispatcher)->updateTheme($themeId, ['test' => ['value' => ['test']]], $parentThemeId, $this->context);
    }

    public function testUpdateThemeWithConfigAndRemovedField(): void
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $dependentThemeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => $themeId,
                    'channelId' => TestDefaults::CHANNEL,
                    'dependentId' => $dependentThemeId,
                    'dchannelId' => TestDefaults::CHANNEL,
                ],
            ]
        );

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection(
                    [
                        new ThemeEntity()->assign(
                            [
                                '_uniqueIdentifier' => $themeId,
                                'channels' => new ChannelCollection(),
                                'configValues' => [
                                    'test' => ['value' => ['no_test']],
                                    'removed' => ['value' => ['still_here']],
                                ],
                                'baseConfig' => [
                                    'fields' => [
                                        'test' => [
                                            'type' => 'string',
                                            'value' => 'test',
                                        ],
                                    ],
                                ],
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $config = [
            'test' => ['value' => ['test']],
            'removed' => ['value' => ['removed']],
        ];

        // Mock the getPlainThemeConfiguration method to return the expected configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->willReturn([
                'fields' => [
                    'test' => [
                        'type' => 'string',
                        'value' => 'test',
                    ],
                ],
            ]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            new ThemeConfigChangedEvent($themeId, ['test' => ['value' => ['test']]], $this->context)
        );

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->exactly(2))->method('compileTheme');

        $this->getThemeService(themeCompiler: $themeCompiler, eventDispatcher: $eventDispatcher)->updateTheme($themeId, $config, $parentThemeId, $this->context);
    }

    public function testUpdateThemeNoChannelAssigned(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection(
                    [
                        new ThemeEntity()->assign(
                            [
                                '_uniqueIdentifier' => $themeId,
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        // Mock the getPlainThemeConfiguration method to return an empty configuration structure.
        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')
            ->willReturn([
                'fields' => [],
            ]);

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->never())->method('compileTheme');

        $this->getThemeService(themeCompiler: $themeCompiler)->updateTheme($themeId, null, null, $this->context);
    }

    public function testUpdateThemeNoTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection([]),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectExceptionObject(ThemeException::couldNotFindThemeById($themeId));

        $this->getThemeService()->updateTheme($themeId, null, null, $this->context);
    }

    public function testResetTheme(): void
    {
        $themeId = Uuid::randomHex();

        $themeRepository = $this->createMock(EntityRepository::class);
        $themeRepository->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection(
                    [
                        new ThemeEntity()->assign(
                            [
                                '_uniqueIdentifier' => $themeId,
                            ]
                        ),
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(
            new ThemeConfigResetEvent($themeId, $this->context)
        );

        $themeRepository->expects($this->once())->method('update')->with(
            [
                [
                    'id' => $themeId,
                    'configValues' => null,
                ],
            ],
            $this->context
        );

        $this->getThemeService(themeRepository: $themeRepository, eventDispatcher: $eventDispatcher)->resetTheme($themeId, $this->context);
    }

    public function testResetThemeNoTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new ThemeCollection([]),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectExceptionObject(ThemeException::couldNotFindThemeById($themeId));
        $this->getThemeService()->resetTheme($themeId, $this->context);
    }

    public function testAsyncCompilationIsSkippedWhenUsingStaticConfigLoader(): void
    {
        $themeId = Uuid::randomHex();
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write(\sprintf('theme-config/%s.json', $themeId), json_encode([
            'styleFiles' => [],
            'scriptFiles' => [],
        ], \JSON_THROW_ON_ERROR));
        $configLoader = new StaticFileConfigLoader($fs);

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->never())->method('get');

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->never())->method('dispatch');

        $themeCompiler = $this->createMock(ThemeCompiler::class);
        $themeCompiler->expects($this->once())->method('compileTheme')->with(
            TestDefaults::CHANNEL,
            $themeId,
            static::anything(),
            static::anything(),
            true,
            $this->context
        );

        $themeService = $this->getThemeService(
            themeCompiler: $themeCompiler,
            configLoader: $configLoader,
            systemConfig: $systemConfig,
            messageBus: $messageBus,
        );

        $themeService->compileTheme(TestDefaults::CHANNEL, $themeId, $this->context);
    }

    public function testValidateThemeConfig(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'ct-color-brand-primary' => [
                'value' => '#ff0000',
            ],
            'ct-non-scss-field' => [
                'value' => '#invalid',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'ct-color-brand-primary' => [
                    'name' => 'ct-color-brand-primary',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
                'ct-ignore-field' => [
                    'name' => 'ct-ignore-field',
                    'type' => 'color',
                    'editable' => false,
                    'scss' => true,
                ],
                'ct-non-scss-field' => [
                    'name' => 'ct-non-scss-field',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => false,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $this->scssCompilerMock->method('compileString')->willReturn('body{background-color:#ff0000;color:darken(#ff0000, 10%)}');

        $result = $this->getThemeService()->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testValidateThemeConfigWithInvalidValues(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'ct-color-brand-primary' => [
                'value' => '#invalid-color',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'ct-color-brand-primary' => [
                    'name' => 'ct-color-brand-primary',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        // Configure the mock to throw an exception when compileString is called
        $this->scssCompilerMock->method('compileString')
            ->willThrowException(new \Exception('Invalid SCSS compilation'));

        $this->expectException(ThemeConfigException::class);

        $this->getThemeService()->validateThemeConfig($themeId, $config, $this->context);
    }

    public function testValidateThemeConfigWithSanitize(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'ct-color-brand-primary' => [
                'value' => '#invalid-color',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'ct-color-brand-primary' => [
                    'name' => 'ct-color-brand-primary',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $this->scssCompilerMock->method('compileString')
            ->willThrowException(new \Exception('Invalid SCSS compilation'));

        $result = $this->getThemeService()->validateThemeConfig($themeId, $config, $this->context, [], true);

        static::assertEquals([
            'ct-color-brand-primary' => [
                'value' => '#ffffff00',
            ],
        ], $result);
    }

    public function testValidateThemeConfigSkipsNonEditableFields(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'ct-non-editable-field' => [
                'value' => '#some-value',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'ct-non-editable-field' => [
                    'name' => 'ct-non-editable-field',
                    'type' => 'color',
                    'editable' => false,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $result = $this->getThemeService()->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testValidateThemeConfigSkipsNonScssFields(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'ct-non-scss-field' => [
                'value' => '#some-value',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'ct-non-scss-field' => [
                    'name' => 'ct-non-scss-field',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => false,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $this->scssCompilerMock->method('compileString')
            ->willThrowException(new \Exception('Invalid SCSS compilation'));

        $result = $this->getThemeService()->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testValidateThemeConfigSkipsNonExistentFields(): void
    {
        $themeId = Uuid::randomHex();

        $config = [
            'ct-non-existent-field' => [
                'value' => '#some-value',
            ],
        ];

        $baseConfig = [
            'fields' => [
                'ct-existing-field' => [
                    'name' => 'ct-existing-field',
                    'type' => 'color',
                    'editable' => true,
                    'scss' => true,
                ],
            ],
        ];

        $this->mergedConfigBuilderMock->method('getPlainThemeConfiguration')->willReturn($baseConfig);

        $result = $this->getThemeService()->validateThemeConfig($themeId, $config, $this->context);

        static::assertEquals($config, $result);
    }

    public function testGetPlainThemeConfiguration(): void
    {
        $themeId = Uuid::randomHex();
        $expectedConfig = ['key' => 'value'];

        $this->mergedConfigBuilderMock
            ->method('getPlainThemeConfiguration')
            ->willReturn($expectedConfig);

        $result = $this->getThemeService()->getPlainThemeConfiguration($themeId, $this->context);

        static::assertSame($expectedConfig, $result);
    }

    public function testGetThemeConfigurationFieldStructure(): void
    {
        $themeId = Uuid::randomHex();
        $expectedConfig = ['structuredKey' => 'structuredValue'];

        $this->mergedConfigBuilderMock
            ->method('getThemeConfigurationFieldStructure')
            ->willReturn($expectedConfig);

        $result = $this->getThemeService()->getThemeConfigurationFieldStructure($themeId, $this->context);

        static::assertSame($expectedConfig, $result);
    }

    /**
     * Builds the subject under test. Every collaborator defaults to the shared stub created in setUp; a test
     * that needs to set call expectations passes a local createMock(...) double in for just that collaborator.
     *
     * @param EntityRepository<ThemeCollection>|null $themeRepository
     * @param EntityRepository<EntityCollection<Entity>>|null $themeChannelRepository
     */
    private function getThemeService(
        ?FrontendPluginRegistry $storefrontPluginRegistry = null,
        ?EntityRepository $themeRepository = null,
        ?EntityRepository $themeChannelRepository = null,
        ?ThemeCompiler $themeCompiler = null,
        ?ScssPhpCompiler $scssCompiler = null,
        ?EventDispatcher $eventDispatcher = null,
        ?AbstractConfigLoader $configLoader = null,
        ?Connection $connection = null,
        ?SystemConfigService $systemConfig = null,
        ?MessageBus $messageBus = null,
        ?NotificationService $notificationService = null,
        ?ThemeMergedConfigBuilder $mergedConfigBuilder = null,
        ?ThemeRuntimeConfigService $runtimeConfigService = null,
    ): ThemeService {
        return new ThemeService(
            $storefrontPluginRegistry ?? $this->storefrontPluginRegistryMock,
            $themeRepository ?? $this->themeRepositoryMock,
            $themeChannelRepository ?? $this->themeChannelRepositoryMock,
            $themeCompiler ?? $this->themeCompilerMock,
            $scssCompiler ?? $this->scssCompilerMock,
            $eventDispatcher ?? $this->eventDispatcherMock,
            $configLoader ?? $this->databaseConfigLoaderMock,
            $connection ?? $this->connectionMock,
            $systemConfig ?? $this->systemConfigMock,
            $messageBus ?? $this->messageBusMock,
            $notificationService ?? static::createStub(NotificationService::class),
            $mergedConfigBuilder ?? $this->mergedConfigBuilderMock,
            $runtimeConfigService ?? $this->runtimeConfigServiceMock,
        );
    }
}
