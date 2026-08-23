<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeChannel;
use Contena\Frontend\Theme\ThemeChannelCollection;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeService;

/**
 * @internal
 */
#[CoversClass(ThemeLifecycleHandler::class)]
class ThemeLifecycleHandlerTest extends TestCase
{
    private ThemeService&Stub $themeServiceMock;

    private FrontendPluginRegistry&Stub $configurationRegistryMock;

    private ThemeLifecycleService $themeLifecycleServiceMock;

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
        $this->themeLifecycleServiceMock = new \ReflectionClass(ThemeLifecycleService::class)->newInstanceWithoutConstructor();
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

    public function testThemeUninstallWithDependentThemes(): void
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
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $themeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
            ],
            [
                [
                    'id' => $themeId,
                    'themeName' => 'Simple Theme',
                    'dthemeName' => 'Dependent On Simple Theme',
                    'dependentId' => Uuid::randomHex(),
                    'channelId' => Uuid::randomHex(),
                    'channelName' => 'ChannelName1',
                    'dchannelId' => Uuid::randomHex(),
                    'dchannelName' => 'ChannelName2',
                ],
                [
                    'id' => $themeId,
                    'themeName' => 'Simple Theme',
                    'dthemeName' => 'Dependent On Simple Theme',
                    'dependentId' => Uuid::randomHex(),
                    'channelId' => Uuid::randomHex(),
                    'channelName' => 'ChannelName1',
                    'dchannelId' => Uuid::randomHex(),
                    'dchannelName' => 'ChannelName2',
                ],
            ]
        );

        $this->themeServiceMock->method('getThemeDependencyMapping')->willReturn(
            new ThemeChannelCollection(
                [
                    new ThemeChannel(Uuid::randomHex(), Uuid::randomHex()),
                ]
            )
        );

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessageMatches('/^Unable to deactivate or uninstall theme/');

        $this->themeLifecycleHandler->handleThemeUninstall(
            $themeConfig,
            $this->context
        );
    }

    public function testAssignmentExceptionInException(): void
    {
        $themeConfig = new FrontendPluginConfiguration('SimpleTheme');
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $themeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
            ],
            null // will throw excepetion to provoke a db exception
        );

        $this->themeServiceMock->method('getThemeDependencyMapping')->willReturn(
            new ThemeChannelCollection(
                [
                    new ThemeChannel(Uuid::randomHex(), Uuid::randomHex()),
                ]
            )
        );

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessageMatches('/^Unable to deactivate or uninstall theme/');

        $this->themeLifecycleHandler->handleThemeUninstall(
            $themeConfig,
            $this->context
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
