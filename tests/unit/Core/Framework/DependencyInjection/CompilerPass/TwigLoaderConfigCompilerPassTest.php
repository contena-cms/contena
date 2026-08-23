<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
#[CoversClass(TwigLoaderConfigCompilerPass::class)]
class TwigLoaderConfigCompilerPassTest extends TestCase
{
    public function testNoBundles(): void
    {
        $container = new ContainerBuilder();

        $filesystemLoaderDefinition = $container->register('twig.loader.native_filesystem', FilesystemLoader::class);
        $container->setParameter('kernel.bundles_metadata', []);
        $entityCompilerPass = new TwigLoaderConfigCompilerPass();
        $entityCompilerPass->process($container);

        static::assertSame([], $filesystemLoaderDefinition->getMethodCalls());
    }

    public function testBundleResourcesAreRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', [
            'pluginOne' => [
                'path' => __DIR__ . '/fixtures/pluginOnePath',
            ],
        ]);

        $filesystemLoaderDefinition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $filesystemLoaderDefinition);

        $entityCompilerPass = new TwigLoaderConfigCompilerPass();
        $entityCompilerPass->process($container);

        static::assertSame([
            ['addPath', [__DIR__ . '/fixtures/pluginOnePath/Resources/views']],
            ['addPath', [__DIR__ . '/fixtures/pluginOnePath/Resources/views', 'pluginOne']],
            ['addPath', [__DIR__ . '/fixtures/pluginOnePath/Resources', 'pluginOne']],
        ], $filesystemLoaderDefinition->getMethodCalls());
    }
}
