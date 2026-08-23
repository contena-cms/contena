<?php declare(strict_types=1);

namespace Contena\Core\DevOps;

use Contena\Core\Framework\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
class DevOps extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.php');

        $environment = $container->getParameter('kernel.environment');
        if ($environment === 'e2e') {
            $loader->load('services_e2e.php');
        }

        if ($environment === 'dev') {
            $loader->load('services_dev.php');
        }
    }
}
