<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test;

use Contena\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 *
 * @method void configureContainer(ContainerBuilder $container, LoaderInterface $loader)
 */
class TestKernel extends Kernel
{
    /**
     * @return \Generator<BundleInterface>
     */
    public function registerBundles(): \Generator
    {
        yield from parent::registerBundles();

        yield new TestBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAbstract()) {
                continue;
            }
            $definition->setPublic(true);
        }
    }
}
