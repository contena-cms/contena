<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Asset;

use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\AdapterException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class AssetPackageService
{
    /**
     * @param array<string, string> $bundleMap
     */
    public static function create(array $bundleMap, AssetPackage $package, VersionStrategyInterface $versionStrategy, mixed ...$args): Packages
    {
        $packages = new Packages(...$args);

        if (!EnvironmentHelper::hasVariable('APP_URL')) {
            return $packages;
        }

        foreach ($bundleMap as $bundleName => $bundlePath) {
            /** @see AssetService::getTargetDirectory() */
            $targetPath = '/bundles/' . preg_replace('/bundle$/D', '', mb_strtolower($bundleName));

            $path = $package->getUrl($targetPath);

            try {
                $bundlePackage = new UrlPackage($path, new PrefixVersionStrategy($targetPath, $versionStrategy));
            } catch (InvalidArgumentException $exception) {
                throw AdapterException::invalidAssetUrl($exception);
            }

            $packages->addPackage('@' . $bundleName, $bundlePackage);
        }

        return $packages;
    }
}
