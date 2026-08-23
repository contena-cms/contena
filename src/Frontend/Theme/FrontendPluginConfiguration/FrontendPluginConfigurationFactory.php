<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\FrontendPluginConfiguration;

use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Parameter\AdditionalBundleParameters;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Frontend\Framework\ThemeInterface;
use Contena\Frontend\Theme\Exception\ThemeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class FrontendPluginConfigurationFactory extends AbstractFrontendPluginConfigurationFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelPluginLoader $pluginLoader,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function getDecorated(): AbstractFrontendPluginConfigurationFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function createFromBundle(Bundle $bundle): FrontendPluginConfiguration
    {
        if ($bundle instanceof ThemeInterface) {
            return $this->createThemeConfig($bundle->getName(), $bundle->getPath());
        }

        $config = $this->createPluginConfig($bundle->getName(), $bundle->getPath());
        if ($bundle instanceof Plugin) {
            $additionalBundleParameters = new AdditionalBundleParameters(
                $this->pluginLoader->getClassLoader(),
                $this->pluginLoader->getPluginInstances(),
                []
            );
            $config->setAdditionalBundles(
                $bundle->getAdditionalBundles($additionalBundleParameters) !== []
            );
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromThemeJson(string $name, array $data, string $path): FrontendPluginConfiguration
    {
        $config = new FrontendPluginConfiguration($name);

        $config->setThemeJson($data);
        $config->setFrontendEntryFilepath($this->getEntryFile($path));
        $config->setIsTheme(true);
        $config->setName($data['name']);
        $config->setAuthor($data['author']);

        if (\array_key_exists('style', $data) && \is_array($data['style'])) {
            $this->resolveStyleFiles($data['style'], $config);
        }

        if (\array_key_exists('script', $data) && \is_array($data['script'])) {
            /** @var list<string> $scriptFiles */
            $scriptFiles = $data['script'];
            $fileCollection = FileCollection::createFromArray($scriptFiles);
            $config->setScriptFiles($fileCollection);
        }

        if (\array_key_exists('asset', $data)) {
            $config->setAssetPaths($data['asset']);
        }

        if (\array_key_exists('previewMedia', $data)) {
            $config->setPreviewMedia($data['previewMedia']);
        }

        if (\array_key_exists('config', $data)) {
            $config->setThemeConfig($data['config']);
        }

        if (\array_key_exists('views', $data)) {
            $config->setViewInheritance($data['views']);
        }

        if (\array_key_exists('configInheritance', $data)) {
            $config->setConfigInheritance($data['configInheritance']);
            $baseConfig = $config->getThemeConfig();
            $baseConfig['configInheritance'] = $data['configInheritance'];
            $config->setThemeConfig($baseConfig);
        }

        if (\array_key_exists('iconSets', $data)) {
            $config->setIconSets($data['iconSets']);
        }

        return $config;
    }

    private function createPluginConfig(string $name, string $path): FrontendPluginConfiguration
    {
        $config = new FrontendPluginConfiguration($name);
        $config->setIsTheme(false);
        $config->setFrontendEntryFilepath($this->getEntryFile($path));

        $stylesPath = $path . '/Resources/app/frontend/src/scss';
        $config->setStyleFiles(FileCollection::createFromArray($this->getScssEntryFileInDir($stylesPath, $path . '/Resources')));

        $assetName = $config->getAssetName();

        $scriptPath = $path . \sprintf('/Resources/app/frontend/dist/frontend/js/%s/%s.js', $assetName, $assetName);

        if (\is_file($scriptPath)) {
            $config->setScriptFiles(FileCollection::createFromArray([$this->stripBasePath($scriptPath, $path . '/Resources')]));

            return $config;
        }

        return $config;
    }

    private function createThemeConfig(string $name, string $path): FrontendPluginConfiguration
    {
        $pathname = $path . \DIRECTORY_SEPARATOR . 'Resources/theme.json';

        if (!\is_file($pathname)) {
            throw ThemeException::invalidThemeBundle($name);
        }

        try {
            $fileContent = $this->filesystem->readFile($pathname);
        } catch (IOException $e) {
            throw ThemeException::themeCompileException($name, 'Unable to read theme.json', $e);
        }

        try {
            $data = json_decode($fileContent, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ThemeException::themeCompileException($name, 'Unable to parse theme.json. Message: ' . $e->getMessage(), $e);
        }

        try {
            $config = $this->createFromThemeJson($name, $data, $path);
        } catch (\Throwable $e) {
            throw ThemeException::themeCompileException(
                $name,
                \sprintf(
                    'Got exception while parsing theme config. Exception message "%s"',
                    $e->getMessage()
                ),
                $e
            );
        }

        return $config;
    }

    private function getEntryFile(string $path): ?string
    {
        $path = rtrim($path, '/') . '/Resources/app/frontend/src';

        if (\is_file($path . '/main.ts')) {
            return 'app/frontend/src/main.ts';
        }

        if (\is_file($path . '/main.js')) {
            return 'app/frontend/src/main.js';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getScssEntryFileInDir(string $path, string $basePath): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $finder = new Finder();
        $finder->files()->name('base.scss')->in($path)->depth('0');

        $files = [];
        foreach ($finder as $file) {
            $files[] = $this->stripBasePath($file->getPathname(), $basePath);
        }

        return $files;
    }

    private function stripBasePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, $basePath)) {
            return substr($path, \strlen($basePath) + 1);
        }

        return $path;
    }

    /**
     * @param array<string|array<array{resolve?: array<string, string>}>> $styles
     */
    private function resolveStyleFiles(array $styles, FrontendPluginConfiguration $config): void
    {
        $fileCollection = new FileCollection();
        foreach ($styles as $style) {
            if (!\is_array($style)) {
                $fileCollection->add(new File($style));

                continue;
            }

            foreach ($style as $filename => $additional) {
                if (!\array_key_exists('resolve', $additional)) {
                    $fileCollection->add(new File($filename));

                    continue;
                }

                $fileCollection->add(new File($filename, $additional['resolve'] ?? []));
            }
        }
        $config->setStyleFiles($fileCollection);
    }
}
