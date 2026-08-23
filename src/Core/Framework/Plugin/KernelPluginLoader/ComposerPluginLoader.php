<?php
declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\KernelPluginLoader;

use Contena\Core\Framework\Adapter\Composer\ComposerInfoProvider;
use Contena\Core\Framework\Plugin\Util\PluginFinder;
use Contena\Core\Framework\Util\IOStreamHelper;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
class ComposerPluginLoader extends KernelPluginLoader
{
    /**
     * @return list<PluginInfo>
     */
    public function fetchPluginInfos(): array
    {
        $this->loadPluginInfos();

        return $this->pluginInfos;
    }

    protected function loadPluginInfos(): void
    {
        $this->pluginInfos = [];

        foreach (ComposerInfoProvider::getComposerPackages(PluginFinder::COMPOSER_TYPE) as $composerPackage) {
            $composerJsonPath = $composerPackage->path . '/composer.json';

            if (!\is_file($composerJsonPath)) {
                continue;
            }

            $composerJsonContent = \file_get_contents($composerJsonPath);
            \assert(\is_string($composerJsonContent));

            $composerJson = \json_decode($composerJsonContent, true, 512, \JSON_THROW_ON_ERROR);
            \assert(\is_array($composerJson));
            $pluginClass = $composerJson['extra']['contena-plugin-class'] ?? '';

            if ($pluginClass === '' || !\class_exists($pluginClass)) {
                IOStreamHelper::writeError(\sprintf('Skipped package %s due invalid "contena-plugin-class" config', $composerPackage->name));

                continue;
            }

            $nameParts = \explode('\\', $pluginClass);

            $this->pluginInfos[] = [
                'name' => array_last($nameParts),
                'baseClass' => $pluginClass,
                'active' => true,
                'path' => $composerPackage->path,
                'version' => $composerPackage->prettyVersion,
                'autoload' => $composerJson['autoload'] ?? [],
                'managedByComposer' => true,
                'composerName' => $composerPackage->name,
            ];
        }
    }
}
