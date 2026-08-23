<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ThemeAssetVersionStrategyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('contena.filesystem.theme.use_last_modified_version_strategy')
            && !$container->getParameter('contena.filesystem.theme.use_last_modified_version_strategy')
        ) {
            $container->removeDefinition('contena.asset.theme.version_strategy');
            $container->setAlias('contena.asset.theme.version_strategy', 'assets.empty_version_strategy');
        }
    }
}
