<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[BecomesInternal(version: 'v6.8.0')]
class AssetRegistrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $assets = [];
        foreach ($container->findTaggedServiceIds('contena.asset') as $id => $config) {
            $container->getDefinition($id)->addTag('assets.package', ['package' => $config[0]['asset']]);
            $assets[$config[0]['asset']] = new Reference($id);
        }

        $assetService = $container->getDefinition('assets.packages');
        $assetService->addMethodCall('setDefaultPackage', [$assets['asset']]);
    }
}
