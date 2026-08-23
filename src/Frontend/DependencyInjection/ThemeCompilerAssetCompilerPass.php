<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Frontend\Theme\ThemeCompiler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the asset packages into Frontend's ThemeCompiler without making Core depend on themes.
 *
 * @internal
 */
class ThemeCompilerAssetCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ThemeCompiler::class)) {
            return;
        }

        $assets = [];
        foreach ($container->findTaggedServiceIds('contena.asset') as $id => $config) {
            $assets[$config[0]['asset']] = new Reference($id);
        }

        $container->getDefinition(ThemeCompiler::class)->setArgument('$packages', $assets);
    }
}
