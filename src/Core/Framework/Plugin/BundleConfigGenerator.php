<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Plugin;
use Contena\Core\Kernel;

/**
 * @phpstan-import-type BundleConfig from BundleConfigGeneratorInterface
 */
class BundleConfigGenerator implements BundleConfigGeneratorInterface
{
    private readonly string $projectDir;

    /**
     * @internal
     */
    public function __construct(
        private readonly Kernel $kernel,
    ) {
        $projectDir = $this->kernel->getContainer()->getParameter('kernel.project_dir');
        if (!\is_string($projectDir)) {
            throw PluginException::invalidContainerParameter('kernel.project_dir', 'string');
        }
        $this->projectDir = $projectDir;
    }

    /**
     * @return array<string, BundleConfig>
     */
    public function getConfig(): array
    {
        return $this->generatePluginConfigs();
    }

    /**
     * @return array<string, BundleConfig>
     */
    private function generatePluginConfigs(): array
    {
        $activePlugins = $this->getActivePlugins();

        $kernelBundles = $this->kernel->getBundles();

        $bundles = [];
        foreach ($kernelBundles as $bundle) {
            // only include contena bundles
            if (!$bundle instanceof Bundle) {
                continue;
            }

            // dont include deactivated plugins
            if ($bundle instanceof Plugin && !\in_array($bundle->getName(), $activePlugins, true)) {
                continue;
            }

            $path = $bundle->getPath();
            if (mb_strpos($bundle->getPath(), $this->projectDir) === 0) {
                // make relative
                $path = \ltrim(\mb_substr($path, \mb_strlen($this->projectDir)), '/');
            }

            $bundles[$bundle->getName()] = [
                'basePath' => $path . '/',
                'views' => ['Resources/views'],
                'technicalName' => \str_replace('_', '-', $bundle->getContainerPrefix()),
                'administration' => [
                    'path' => 'Resources/app/administration/src',
                    'entryFilePath' => $this->getEntryFile($bundle->getPath(), 'Resources/app/administration/src'),
                    'webpack' => $this->getWebpackConfig($bundle->getPath(), 'Resources/app/administration'),
                ],
            ];
        }

        return $bundles;
    }

    private function getEntryFile(string $rootPath, string $componentPath): ?string
    {
        $path = trim($componentPath, '/');
        $absolutePath = $rootPath . '/' . $path;

        foreach (['js', 'ts'] as $type) {
            if (\is_file($absolutePath . '/main.' . $type)) {
                return $path . '/main.' . $type;
            }
        }

        return null;
    }

    private function getWebpackConfig(string $rootPath, string $componentPath): ?string
    {
        $path = trim($componentPath, '/');
        $absolutePath = $rootPath . '/' . $path;

        $configFileName = match (true) {
            \is_file($absolutePath . '/build/webpack.config.ts') => 'webpack.config.ts',
            \is_file($absolutePath . '/build/webpack.config.cts') => 'webpack.config.cts',
            \is_file($absolutePath . '/build/webpack.config.js') => 'webpack.config.js',
            \is_file($absolutePath . '/build/webpack.config.cjs') => 'webpack.config.cjs',
            default => null,
        };

        if ($configFileName === null) {
            return null;
        }

        if (mb_strpos($path, $this->projectDir) === 0) {
            // make relative
            $path = ltrim(mb_substr($path, mb_strlen($this->projectDir)), '/');
        }

        return $path . '/build/' . $configFileName;
    }

    /**
     * @return array<string>
     */
    private function getActivePlugins(): array
    {
        $activePlugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();

        return array_map(static fn (Plugin $plugin) => $plugin->getName(), $activePlugins);
    }
}
