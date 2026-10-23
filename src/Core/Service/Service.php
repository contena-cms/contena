<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\Bundle;
use Contena\Core\Service\DependencyInjection\ServiceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class Service extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new ServiceExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.php');
        parent::build($container);
    }
}
