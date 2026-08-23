<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Util\Filesystem;
use Contena\Core\Kernel;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
class ThemeFilesystemResolver
{
    /**
     * @var list<string>
     */
    private readonly array $bundleNames;

    public function __construct(private readonly Kernel $kernel)
    {
        // get all bundles + plugin names
        // we need to do this because at boot a plugin might not be active (eg during plugin:activate command) and thus not in `getBundles()`
        // but getPluginInstances does not include local bundles (eg Frontend)
        $this->bundleNames = array_values(array_unique(array_merge(
            array_keys($this->kernel->getBundles()),
            array_map(static fn (Plugin $plugin): string => $plugin->getName(), $this->kernel->getPluginLoader()->getPluginInstances()->all())
        )));
    }

    public function getFilesystemForFrontendConfig(FrontendPluginConfiguration $configuration): Filesystem
    {
        if (!\in_array($configuration->getTechnicalName(), $this->bundleNames, true)) {
            throw ThemeException::missingBundlePath($configuration->getTechnicalName());
        }

        try {
            $bundle = $this->kernel->getBundle($configuration->getTechnicalName());
        } catch (\InvalidArgumentException) {
            $bundles = $this->kernel->getPluginLoader()
                ->getPluginInstances()
                ->filter(static fn (Plugin $plugin) => $plugin->getName() === $configuration->getTechnicalName())
                ->all();

            $bundle = array_values($bundles)[0];
        }

        \assert($bundle instanceof BundleInterface);

        return new Filesystem($bundle->getPath());
    }
}
