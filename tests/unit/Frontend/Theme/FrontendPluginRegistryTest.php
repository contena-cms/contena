<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Bundle;
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
    public function testGetByTechnicalNameLoadsSinglePlugin(): void
    {
        $pluginFactory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);

        $configuration = new FrontendPluginConfiguration('Plugin1');
        $bundle = new class extends Bundle {
            protected string $name = 'Plugin1';
        };

        $pluginFactory->expects($this->once())
            ->method('createFromBundle')
            ->with($bundle)
            ->willReturn($configuration);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getBundles')
            ->willReturn([$bundle]);

        $registry = new FrontendPluginRegistry($kernel, $pluginFactory);

        static::assertSame($configuration, $registry->getByTechnicalName('Plugin1'));
    }

    public function testGetConfigurationsLoadsAllBundles(): void
    {
        $pluginFactory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);

        $firstConfiguration = new FrontendPluginConfiguration('Plugin1');
        $secondConfiguration = new FrontendPluginConfiguration('Plugin2');
        $firstBundle = new class extends Bundle {
            protected string $name = 'Plugin1';
        };
        $secondBundle = new class extends Bundle {
            protected string $name = 'Plugin2';
        };

        $pluginFactory->expects($this->exactly(2))
            ->method('createFromBundle')
            ->willReturnMap([
                [$firstBundle, $firstConfiguration],
                [$secondBundle, $secondConfiguration],
            ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getBundles')
            ->willReturn([$firstBundle, new \stdClass(), $secondBundle]);

        $registry = new FrontendPluginRegistry($kernel, $pluginFactory);

        $configurations = $registry->getConfigurations();

        static::assertCount(2, $configurations);
        static::assertSame($firstConfiguration, $configurations->getByTechnicalName('Plugin1'));
        static::assertSame($secondConfiguration, $configurations->getByTechnicalName('Plugin2'));
    }
}
