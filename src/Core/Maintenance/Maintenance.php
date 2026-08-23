<?php declare(strict_types=1);

namespace Contena\Core\Maintenance;

use Contena\Core\Framework\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
class Maintenance extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.php');
    }
}
