<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeAppLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleService;

/**
 * @internal
 */
#[CoversClass(ThemeAppLifecycleHandler::class)]
class ThemeAppLifecycleHandlerTest extends TestCase
{
    private FrontendPluginRegistry&Stub $registry;

    private AbstractFrontendPluginConfigurationFactory&Stub $factory;

    private ThemeLifecycleHandler&Stub $themeLifecycle;

    private ThemeLifecycleService&Stub $themeService;

    protected function setUp(): void
    {
        $this->registry = static::createStub(FrontendPluginRegistry::class);
        $this->factory = static::createStub(AbstractFrontendPluginConfigurationFactory::class);
        $this->themeLifecycle = static::createStub(ThemeLifecycleHandler::class);
        $this->themeService = static::createStub(ThemeLifecycleService::class);
    }

    public function testActivateUsesExistingConfiguration(): void
    {
        $config = new FrontendPluginConfiguration('ComponentTestApp');
        $configurations = new FrontendPluginConfigurationCollection([$config]);
        $this->registry->method('getConfigurations')->willReturn($configurations);

        $factory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);
        $factory->expects($this->never())->method('createFromApp');

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())
            ->method('handleThemeInstallOrUpdate')
            ->with($config, $configurations, static::isInstanceOf(Context::class));
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle);
        $handler->activate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testActivateCreatesConfigurationWhenMissing(): void
    {
        $this->registry->method('getConfigurations')->willReturn(new FrontendPluginConfigurationCollection());

        $factory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn(new FrontendPluginConfiguration('ComponentTestApp'));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())->method('handleThemeInstallOrUpdate');
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle);
        $handler->activate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testActivateSkipsInactiveApp(): void
    {
        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->never())->method('handleThemeInstallOrUpdate');
        $themeLifecycle->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $handler = $this->getHandler(themeLifecycle: $themeLifecycle);
        $app = (new AppEntity())->assign(['name' => 'ComponentTestApp', 'path' => 'p', 'active' => false]);
        $handler->activate(new AppActivationContext($app, Context::createDefaultContext()));
    }

    public function testUpdateSetsUpTheme(): void
    {
        $config = new FrontendPluginConfiguration('ComponentTestApp');
        $this->registry->method('getConfigurations')->willReturn(new FrontendPluginConfigurationCollection([$config]));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())->method('handleThemeInstallOrUpdate');
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $context = new AppPersistContext(
            static::createStub(Manifest::class),
            $this->activeApp(),
            Context::createDefaultContext(),
            new StaticFilesystem(),
            'en-GB',
        );
        $this->getHandler(themeLifecycle: $themeLifecycle)->update($context);
    }

    public function testDeactivateTearsDownConfigBuiltFromApp(): void
    {
        $builtConfig = new FrontendPluginConfiguration('ComponentTestApp');
        $factory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn($builtConfig);

        // the registry still lists the app's own config plus another; the app's own one must be filtered out
        $this->registry->method('getConfigurations')->willReturn(new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('ComponentTestApp'),
            new FrontendPluginConfiguration('OtherApp'),
        ]));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())
            ->method('handleThemeUninstall')
            ->with($builtConfig, static::isInstanceOf(Context::class));
        $themeLifecycle->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with(
                static::isInstanceOf(Context::class),
                static::callback(static fn (FrontendPluginConfigurationCollection $c): bool => $c->getByTechnicalName('ComponentTestApp') === null && $c->getByTechnicalName('OtherApp') !== null),
            );

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle);
        $handler->deactivate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testUninstallRemovesThemeRecord(): void
    {
        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->never())->method('handleThemeUninstall');

        $themeService = $this->createMock(ThemeLifecycleService::class);
        $themeService->expects($this->once())
            ->method('removeTheme')
            ->with('ComponentTestApp', static::isInstanceOf(Context::class));

        $handler = $this->getHandler(themeLifecycle: $themeLifecycle, themeService: $themeService);
        $handler->uninstall(new AppRemovalContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testUninstallKeepsRecordWhenUserDataKept(): void
    {
        $themeService = $this->createMock(ThemeLifecycleService::class);
        $themeService->expects($this->never())->method('removeTheme');

        $handler = $this->getHandler(themeService: $themeService);
        $handler->uninstall(new AppRemovalContext($this->activeApp(), Context::createDefaultContext(), keepUserData: true));
    }

    public function testDeleteTearsDownConfigButKeepsRecord(): void
    {
        $this->registry->method('getConfigurations')->willReturn(new FrontendPluginConfigurationCollection());

        $factory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromApp')
            ->willReturn(new FrontendPluginConfiguration('ComponentTestApp'));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())->method('handleThemeUninstall');
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $themeService = $this->createMock(ThemeLifecycleService::class);
        $themeService->expects($this->never())->method('removeTheme');

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle, themeService: $themeService);
        $handler->delete(new AppRemovalContext($this->activeApp(), Context::createDefaultContext()));
    }

    private function getHandler(
        (AbstractFrontendPluginConfigurationFactory&MockObject)|null $factory = null,
        (ThemeLifecycleHandler&MockObject)|null $themeLifecycle = null,
        (ThemeLifecycleService&MockObject)|null $themeService = null,
    ): ThemeAppLifecycleHandler {
        return new ThemeAppLifecycleHandler(
            $this->registry,
            $factory ?? $this->factory,
            $themeLifecycle ?? $this->themeLifecycle,
            $themeService ?? $this->themeService,
        );
    }

    private function activeApp(): AppEntity
    {
        return (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);
    }
}
