<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Contena\Core\Framework\DependencyInjection\CompilerPass\CompilerPassConfigTrait;

#[BecomesInternal(version: 'v6.8.0')]
class HttpCacheConfigCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        $config = $this->getConfig($container, 'framework');

        $container->getDefinition('http_kernel.cache')
            ->replaceArgument(3, $config['http_cache'] ?? []);
    }
}
