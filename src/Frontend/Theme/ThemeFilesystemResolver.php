<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin;
use Contena\Core\Kernel;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeFilesystemResolver
{
    /**
     * @var list<string>
     */
    private readonly array $bundleNames;

    public function __construct(
        private readonly SourceResolver $sourceResolver,
        private readonly Kernel $kernel,
    ) {
        // get all bundles + plugin names
        // we need to do this because at boot a plugin might not be active (eg during plugin:activate command) and thus not in `getBundles()`
        // but getPluginInstances does not include local bundles (eg Storefront)
        $this->bundleNames = array_values(array_unique(array_merge(
            array_keys($this->kernel->getBundles()),
            array_map(static fn (Plugin $plugin): string => $plugin->getName(), $this->kernel->getPluginLoader()->getPluginInstances()->all())
        )));
    }

}
