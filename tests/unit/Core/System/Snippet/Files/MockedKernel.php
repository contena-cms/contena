<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Files;

use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 *
 * @method void configureContainer(ContainerBuilder $container, LoaderInterface $loader)
 */
class MockedKernel extends Kernel
{
    /**
     * @param array<string, BundleInterface> $bundles
     */
    public function __construct(array $bundles, ?KernelPluginLoader $pluginLoader = null)
    {
        $this->bundles = $bundles;

        if ($pluginLoader) {
            $this->pluginLoader = $pluginLoader;
        }
    }
}
