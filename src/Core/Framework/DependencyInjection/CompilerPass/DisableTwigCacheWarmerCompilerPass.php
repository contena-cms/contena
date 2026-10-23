<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[BecomesInternal(version: 'v6.8.0')]
class DisableTwigCacheWarmerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->removeDefinition('twig.template_cache_warmer');
    }
}
