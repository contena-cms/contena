<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\StorefrontPluginConfiguration;

use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[Package('discovery')]
class StorefrontPluginConfigurationFactory extends AbstractStorefrontPluginConfigurationFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelPluginLoader $pluginLoader,
        private readonly SourceResolver $sourceResolver,
        private readonly Filesystem $filesystem,
    ) {
    }







    private function getEntryFile(string $path): ?string
    {
        $path = rtrim($path, '/') . '/Resources/app/storefront/src';

        if (\is_file($path . '/main.ts')) {
            return 'app/storefront/src/main.ts';
        }

        if (\is_file($path . '/main.js')) {
            return 'app/storefront/src/main.js';
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

}
