<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Kernel;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Contena\Tests\Unit\Frontend\Theme\fixtures\MockFrontend\MockFrontend;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[CoversClass(ThemeFilesystemResolver::class)]
class ThemeFilesystemResolverTest extends TestCase
{
    public function testGetFilesystemForFrontendUsesBundleRootWithoutResourcePrefix(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $bundle = new MockFrontend();
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'Frontend' => $bundle,
        ]);

        $kernel->expects($this->once())->method('getBundle')->willReturnMap([
            ['Frontend', $bundle],
        ]);

        $resolver = new ThemeFilesystemResolver($kernel);

        $pluginConfig = new FrontendPluginConfiguration('Frontend');
        $fs = $resolver->getFilesystemForFrontendConfig($pluginConfig);

        static::assertSame($bundle->getPath(), $fs->location);
    }

    public function testGetFilesystemRejectsUnknownConfiguration(): void
    {
        $resolver = new ThemeFilesystemResolver(static::createStub(Kernel::class));
        $pluginConfig = new FrontendPluginConfiguration('UnknownFrontendBundle');

        $this->expectExceptionObject(ThemeException::missingBundlePath('UnknownFrontendBundle'));

        $resolver->getFilesystemForFrontendConfig($pluginConfig);
    }

    public function testGetFilesystemForPluginUsesBundleBasePath(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->expects($this->once())->method('getPath')->willReturn('/some/project/custom/plugins/CoolPlugin');
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'CoolPlugin' => $bundle,
        ]);

        $kernel->expects($this->once())->method('getBundle')->willReturnMap([
            ['CoolPlugin', $bundle],
        ]);

        $resolver = new ThemeFilesystemResolver($kernel);

        $pluginConfig = new FrontendPluginConfiguration('CoolPlugin');

        $fs = $resolver->getFilesystemForFrontendConfig($pluginConfig);

        static::assertSame('/some/project/custom/plugins/CoolPlugin', $fs->location);
    }
}
