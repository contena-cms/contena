<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Adapter\Session\SessionFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class OverwriteSessionFactoryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition('session.factory');
        $definition->setClass(SessionFactory::class);
    }
}
