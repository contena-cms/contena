<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(FrontendPluginRegistry::class)]
class FrontendPluginRegistryTest extends TestCase
{
    public function testGetByTechnicalNameLoadsSingleApp(): void
    {
        $appLoader = $this->createMock(ActiveAppsLoader::class);
        $appLoader->expects($this->once())
            ->method('getActiveApps')
            ->willReturn([
                [
                    'name' => 'App1',
                    'path' => 'App1',
                    'author' => 'App1',
                    'selfManaged' => false,
                ],
                [
                    'name' => 'App2',
                    'path' => 'App2',
                    'author' => 'App2',
                    'selfManaged' => false,
                ],
            ]);

        $pluginFactory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);

        $config = new FrontendPluginConfiguration('App1');
        $pluginFactory->expects($this->once())
            ->method('createFromApp')
            ->with('App1', 'App1')
            ->willReturn($config);

        $registry = new FrontendPluginRegistry(
            static::createStub(KernelInterface::class),
            $pluginFactory,
            $appLoader
        );

        static::assertSame(
            $config,
            $registry->getByTechnicalName('App1')
        );
    }

    public function testGetByTechnicalNameLoadsSinglePlugin(): void
    {
        $pluginFactory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);

        $config = new FrontendPluginConfiguration('Plugin1');
        $bundle = new class extends Bundle {
            protected string $name = 'Plugin1';
        };

        $pluginFactory->expects($this->once())
            ->method('createFromBundle')
            ->with($bundle)
            ->willReturn($config);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getBundles')
            ->willReturn([$bundle]);

        $registry = new FrontendPluginRegistry(
            $kernel,
            $pluginFactory,
            static::createStub(ActiveAppsLoader::class)
        );

        static::assertSame(
            $config,
            $registry->getByTechnicalName('Plugin1')
        );
    }

    public function testGetConfigurationsExcludesServices(): void
    {
        $appLoader = $this->createMock(ActiveAppsLoader::class);
        $appLoader->expects($this->once())
            ->method('getActiveApps')
            ->willReturn([
                [
                    'name' => 'App1',
                    'path' => 'App1',
                    'author' => 'App1',
                    'selfManaged' => false,
                ],
                [
                    'name' => 'App2',
                    'path' => 'App2',
                    'author' => 'App2',
                    'selfManaged' => true,
                ],
            ]);

        $pluginFactory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);

        $config = new FrontendPluginConfiguration('App1');
        $pluginFactory->expects($this->once())
            ->method('createFromApp')
            ->with('App1', 'App1')
            ->willReturn($config);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getBundles')
            ->willReturn([]);

        $registry = new FrontendPluginRegistry(
            $kernel,
            $pluginFactory,
            $appLoader
        );

        $configs = $registry->getConfigurations();

        static::assertCount(1, $configs);
        static::assertTrue($configs->has('App1'));
    }
}
