<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DependencyInjection\CompilerPass;

use Contena\Core\Content\Category\Service\NavigationLoader;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 * Marks services public that would otherwise be inlined in setups where only Contena/Core is used,
 * as the only usages are in frontend
 */
class ContainerVisibilityCompilerPass implements CompilerPassInterface
{
    private const PUBLIC_TEST_SERVICES = [
        NavigationLoader::class,
        HreflangLoaderInterface::class,
        SeoUrlUpdater::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::PUBLIC_TEST_SERVICES as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $definition->setPublic(true);
        }
    }
}
