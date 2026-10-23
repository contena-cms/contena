<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Util;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Composer\PackageProvider;
use Symfony\Component\Finder\Finder;

class PluginFinder
{
    final public const COMPOSER_TYPE = 'contena-platform-plugin';
    private const CONTENA_PLUGIN_CLASS_EXTRA_IDENTIFIER = 'contena-plugin-class';

    /**
     * @internal
     */
    public function __construct(private readonly PackageProvider $packageProvider)
    {
    }



    private function isContenaPluginType(CompletePackageInterface $package): bool
    {
        return $package->getType() === self::COMPOSER_TYPE;
    }

    private function isPluginComposerValid(CompletePackageInterface $package): bool
    {
        return isset($package->getExtra()[self::CONTENA_PLUGIN_CLASS_EXTRA_IDENTIFIER])
            && $package->getExtra()[self::CONTENA_PLUGIN_CLASS_EXTRA_IDENTIFIER] !== ''
            && !empty($package->getExtra()['label']);
    }

    private function getPluginNameFromPackage(CompletePackageInterface $pluginPackage): string
    {
        return $pluginPackage->getExtra()[self::CONTENA_PLUGIN_CLASS_EXTRA_IDENTIFIER];
    }


    private function getVendorPluginPath(CompletePackageInterface $pluginPackage, Composer $composer): string
    {
        return $composer->getConfig()->get('vendor-dir') . '/' . $pluginPackage->getPrettyName();
    }

}
