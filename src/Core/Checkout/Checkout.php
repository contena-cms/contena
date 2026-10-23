<?php declare(strict_types=1);

namespace Contena\Core\Checkout;

use Contena\Core\Framework\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
class Checkout extends Bundle
{
    private const DEPENDENCY_LOCATION = __DIR__ . '/DependencyInjection/';

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator(self::DEPENDENCY_LOCATION);

        $phpLoader = new PhpFileLoader($container, $locator);
        $phpLoader->load('user.php');
    }
}
