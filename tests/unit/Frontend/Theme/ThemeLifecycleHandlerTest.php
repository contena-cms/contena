<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeChannel;
use Contena\Frontend\Theme\ThemeChannelCollection;
use Contena\Frontend\Theme\ThemeService;

/**
 * @internal
 */
#[CoversClass(ThemeLifecycleHandler::class)]
class ThemeLifecycleHandlerTest extends TestCase
{
    private ThemeService&Stub $themeServiceMock;

    private FrontendPluginRegistry&Stub $configurationRegistryMock;

    private ThemeLifecycleService&Stub $themeLifecycleServiceMock;

    /**
     * @var EntityRepository<ThemeCollection>&Stub
     */
    private EntityRepository&Stub $themeRepositoryMock;

    private Connection&Stub $connectionMock;

    private ThemeLifecycleHandler $themeLifecycleHandler;

    private Context $context;

    protected function setUp(): void
    {
        $this->themeServiceMock = static::createStub(ThemeService::class);
        $this->configurationRegistryMock = static::createStub(FrontendPluginRegistry::class);
        $this->themeLifecycleServiceMock = static::createStub(ThemeLifecycleService::class);
        $this->themeRepositoryMock = static::createStub(EntityRepository::class);
        $this->connectionMock = static::createStub(Connection::class);

        $this->themeLifecycleHandler = new ThemeLifecycleHandler(
            $this->themeLifecycleServiceMock,
            $this->themeServiceMock,
            $this->themeRepositoryMock,
            $this->configurationRegistryMock,
            $this->connectionMock
        );

        $this->context = Context::createDefaultContext();
    }

    public function testThemeUninstallWithoutData(): void
    {
        $themeConfig = new FrontendPluginConfiguration('SimpleTheme');
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $collection = new FrontendPluginConfigurationCollection([
            $themeConfig,
        ]);

        $configurationRegistryMock = $this->createMock(FrontendPluginRegistry::class);
        $configurationRegistryMock->expects($this->once())->method('getConfigurations')->willReturn(
            $collection
        );

        $themeRepositoryMock = $this->createMock(EntityRepository::class);
        $themeRepositoryMock->expects($this->never())->method('upsert');

        $this->buildHandler(
            themeRepository: $themeRepositoryMock,
            configurationRegistry: $configurationRegistryMock
        )->handleThemeUninstall(
            $themeConfig,
            $this->context
        );
    }

    public function testThemeUninstallWithDependendThemes(): void
    {
        $themeConfig = new FrontendPluginConfiguration('SimpleTheme');
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $collection = new FrontendPluginConfigurationCollection([
            $themeConfig,
        ]);

        $configurationRegistryMock = $this->createMock(FrontendPluginRegistry::class);
        $configurationRegistryMock->expects($this->once())->method('getConfigurations')->willReturn(
            $collection
        );

        $themeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn([
            [
                'id' => $themeId,
                'dependentId' => Uuid::randomHex(),
            ],
            [
                'id' => $themeId,
                'dependentId' => Uuid::randomHex(),
            ],
        ]);

        $themeRepositoryMock = $this->createMock(EntityRepository::class);
        $themeRepositoryMock->expects($this->once())->method('upsert');

        $this->buildHandler(
            themeRepository: $themeRepositoryMock,
            configurationRegistry: $configurationRegistryMock
        )->handleThemeUninstall(
            $themeConfig,
            $this->context
        );
    }

    public function testAssignmentException(): void
    {
        $themeConfig = new FrontendPluginConfiguration('SimpleTheme');
        $themeConfig->setIsTheme(true);
        $themeId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $childChannelId = Uuid::randomHex();
        $this->connectionMock->method('fetchAllAssociative')->willReturn([
            ['id' => $themeId, 'dependentId' => $childId],
        ]);
        $this->connectionMock->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            [$themeId => 'Simple Theme', $childId => 'Dependent On Simple Theme'],
            [$channelId => 'Frontend DE', $childChannelId => 'Frontend EN'],
        );
        $this->themeServiceMock->method('getThemeDependencyMapping')->willReturn(new ThemeChannelCollection([
            new ThemeChannel($themeId, $channelId),
            new ThemeChannel($childId, $childChannelId),
        ]));
        $this->expectExceptionObject(ThemeException::themeAssignmentException(
            'Simple Theme',
            ['Simple Theme' => [$channelId]],
            ['Dependent On Simple Theme' => [$childChannelId]],
            [$channelId => 'Frontend DE', $childChannelId => 'Frontend EN'],
        ));

        $this->themeLifecycleHandler->handleThemeUninstall($themeConfig, $this->context);
    }

    public function testAssignmentExceptionInException(): void
    {
        $themeConfig = new FrontendPluginConfiguration('SimpleTheme');
        $themeConfig->setIsTheme(true);
        $themeId = Uuid::randomHex();
        $this->connectionMock->method('fetchAllAssociative')->willReturn([
            ['id' => $themeId, 'dependentId' => Uuid::randomHex()],
        ]);
        $failure = new \RuntimeException('Cannot load assignment names');
        $this->connectionMock->method('fetchAllKeyValue')->willThrowException($failure);
        $this->themeServiceMock->method('getThemeDependencyMapping')->willReturn(new ThemeChannelCollection([
            new ThemeChannel($themeId, Uuid::randomHex()),
        ]));
        $this->expectExceptionObject(ThemeException::themeAssignmentException($themeId, [], [], [], $failure));

        $this->themeLifecycleHandler->handleThemeUninstall($themeConfig, $this->context);
    }

    public function testSkipThemeCompilationIfContextStateIsSet(): void
    {
        $config = new FrontendPluginConfiguration('simple-theme');
        $config->setIsTheme(true);

        $context = Context::createDefaultContext();
        $context->addState(AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION);

        $themeLifecycleServiceMock = $this->createMock(ThemeLifecycleService::class);
        $themeLifecycleServiceMock
            ->expects($this->once())
            ->method('refreshTheme')
            ->with($config, $context);

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $themeServiceMock = $this->createMock(ThemeService::class);
        $themeServiceMock->expects($this->never())->method('compileThemeById');
        $themeServiceMock->expects($this->never())->method('compileTheme');

        $this->buildHandler(
            themeLifecycleService: $themeLifecycleServiceMock,
            themeService: $themeServiceMock,
            connection: $connectionMock
        )->handleThemeInstallOrUpdate(
            $config,
            new FrontendPluginConfigurationCollection([$config]),
            $context,
        );
    }

    public function testRefreshAllActiveThemeImportMaps(): void
    {
        $configurationCollection = new FrontendPluginConfigurationCollection();

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['channel_id' => Uuid::randomHex(), 'theme_id' => Uuid::randomHex()],
                ['channel_id' => Uuid::randomHex(), 'theme_id' => Uuid::randomHex()],
            ]);

        $themeServiceMock = $this->createMock(ThemeService::class);
        $themeServiceMock
            ->expects($this->exactly(2))
            ->method('refreshThemeImportMap');

        $this->buildHandler(
            themeService: $themeServiceMock,
            connection: $connectionMock
        )->refreshAllActiveThemeImportMaps($this->context, $configurationCollection);
    }

    /**
     * @param (EntityRepository<ThemeCollection>&MockObject)|null $themeRepository
     */
    private function buildHandler(
        ?ThemeLifecycleService $themeLifecycleService = null,
        ?ThemeService $themeService = null,
        ?EntityRepository $themeRepository = null,
        ?FrontendPluginRegistry $configurationRegistry = null,
        ?Connection $connection = null
    ): ThemeLifecycleHandler {
        return new ThemeLifecycleHandler(
            $themeLifecycleService ?? $this->themeLifecycleServiceMock,
            $themeService ?? $this->themeServiceMock,
            $themeRepository ?? $this->themeRepositoryMock,
            $configurationRegistry ?? $this->configurationRegistryMock,
            $connection ?? $this->connectionMock
        );
    }
}
