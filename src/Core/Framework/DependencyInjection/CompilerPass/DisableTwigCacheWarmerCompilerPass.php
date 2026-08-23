<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DisableTwigCacheWarmerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->removeDefinition('twig.template_cache_warmer');
    }
}
