<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Contena\Core\Framework\Plugin\Exception\PluginExtractionException;
use Contena\Core\Framework\Plugin\Util\ZipUtils;

/**
 * @internal
 */
class PluginZipDetector
{
    /**
     * @return PluginManagementService::PLUGIN
     */
    public function detect(string $zipFilePath): string
    {
        try {
            $archive = ZipUtils::openZip($zipFilePath);
        } catch (PluginExtractionException) {
            throw PluginException::noPluginFoundInZip($zipFilePath);
        }

        try {
            return $this->isPlugin($archive)
                ? PluginManagementService::PLUGIN
                : throw PluginException::noPluginFoundInZip($zipFilePath);
        } finally {
            $archive->close();
        }
    }

    public function isPlugin(\ZipArchive $archive): bool
    {
        $entry = $archive->statIndex(0);
        if ($entry === false) {
            return false;
        }

        $pluginName = explode('/', (string) $entry['name'])[0];
        $composerFile = $pluginName . '/composer.json';
        $manifestFile = $pluginName . '/manifest.xml';

        $statComposerFile = $archive->statName($composerFile);
        $statManifestFile = $archive->statName($manifestFile);

        return $statComposerFile !== false && $statManifestFile === false;
    }
}
