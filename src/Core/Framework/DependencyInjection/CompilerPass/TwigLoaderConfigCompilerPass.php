<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class TwigLoaderConfigCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $fileSystemLoader = $container->findDefinition('twig.loader.native_filesystem');

        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!\is_array($bundlesMetadata)) {
            throw DependencyInjectionException::bundlesMetadataIsNotAnArray();
        }

        foreach ($bundlesMetadata as $name => $bundle) {
            $resourcesDirectory = $bundle['path'] . '/Resources';
            $viewDirectory = $resourcesDirectory . '/views';

            if (\is_dir($viewDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$viewDirectory]);
                $fileSystemLoader->addMethodCall('addPath', [$viewDirectory, $name]);
            }

            if (\is_dir($resourcesDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$resourcesDirectory, $name]);
            }
        }
    }
}
