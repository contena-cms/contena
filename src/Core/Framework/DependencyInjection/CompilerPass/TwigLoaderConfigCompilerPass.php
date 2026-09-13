<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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
            $distDirectory = $resourcesDirectory . '/app/frontend/dist';

            if (\is_dir($viewDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$viewDirectory]);
                $fileSystemLoader->addMethodCall('addPath', [$viewDirectory, $name]);
            }

            if (\is_dir($distDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$distDirectory, $name]);
            }

            if (\is_dir($resourcesDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$resourcesDirectory, $name]);
            }
        }

        // App templates are loaded from files only in dev; production reads the active templates from DB.
        if ($container->getParameter('kernel.environment') === 'dev') {
            $this->addAppTemplatePaths($container, $fileSystemLoader);
        }
    }

    private function addAppTemplatePaths(ContainerBuilder $container, Definition $fileSystemLoader): void
    {
        $connection = $container->get(Connection::class);

        try {
            $apps = $connection->fetchAllAssociative('SELECT `name`, `path` FROM `app` WHERE `active` = 1');
        } catch (Exception) {
            return;
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if (!\is_string($projectDir)) {
            throw DependencyInjectionException::projectDirNotInContainer();
        }

        foreach ($apps as $app) {
            \assert(\is_string($app['path']));
            $resourcesDirectory = \sprintf('%s/%s/Resources', $projectDir, $app['path']);
            $viewDirectory = $resourcesDirectory . '/views';
            $distDirectory = $resourcesDirectory . '/app/frontend/dist';

            if (\is_dir($viewDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$viewDirectory]);
                $fileSystemLoader->addMethodCall('addPath', [$viewDirectory, $app['name']]);
            }

            if (\is_dir($distDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$distDirectory, $app['name']]);
            }

            if (\is_dir($resourcesDirectory)) {
                $fileSystemLoader->addMethodCall('addPath', [$resourcesDirectory, $app['name']]);
            }
        }
    }
}
