<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Composer\IO\NullIO;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Context;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
class PluginManagementService
{
    final public const string PLUGIN = 'plugin';

    public function __construct(
        private readonly string $projectDir,
        private readonly PluginZipDetector $pluginZipDetector,
        private readonly ExtensionExtractor $extensionExtractor,
        private readonly PluginService $pluginService,
        private readonly Filesystem $filesystem,
        private readonly CacheClearer $cacheClearer,
    ) {
    }

    public function extractPluginZip(string $file, bool $delete = true): string
    {
        $type = $this->pluginZipDetector->detect($file);
        $this->extractPlugin($file, $delete);

        return $type;
    }

    public function uploadPlugin(UploadedFile $file, Context $context): void
    {
        $tempFileName = tempnam(sys_get_temp_dir(), $file->getClientOriginalName());
        if (!\is_string($tempFileName)) {
            throw PluginException::cannotCreateTemporaryDirectory(sys_get_temp_dir(), $file->getClientOriginalName());
        }
        $tempRealPath = realpath($tempFileName);
        \assert(\is_string($tempRealPath));
        $tempDirectory = \dirname($tempRealPath);

        $tempFile = $file->move($tempDirectory, $tempFileName);

        $type = $this->extractPluginZip($tempFile->getPathname());

        if ($type === self::PLUGIN) {
            $this->pluginService->refreshPlugins($context, new NullIO());
        }
    }

    public function deletePlugin(PluginEntity $plugin, Context $context): void
    {
        // when `executeComposerCommands` is set to true `managedByComposer` will be true even for plugins installed via the admin
        // so we need to check the path as well and allow removal of plugins in `custom/plugins` folder
        if ($plugin->getManagedByComposer() && !$plugin->isLocatedInCustomPluginDirectory()) {
            throw PluginException::cannotDeleteManaged($plugin->getName());
        }

        $path = $this->projectDir . '/' . $plugin->getPath();
        $this->filesystem->remove($path);

        $this->pluginService->refreshPlugins($context, new NullIO());
    }

    private function extractPlugin(string $fileName, bool $delete): void
    {
        $this->extensionExtractor->extract($fileName, $delete, self::PLUGIN);
        $this->cacheClearer->clearContainerCache();
    }
}
