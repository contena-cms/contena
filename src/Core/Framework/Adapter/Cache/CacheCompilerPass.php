<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Contena\Core\Framework\Adapter\AdapterException;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[BecomesInternal(version: 'v6.8.0')]
class CacheCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $storage = $container->getParameter('contena.cache.invalidation.delay_options.storage');

        switch ($storage) {
            case 'mysql':
                $container->removeDefinition('contena.cache.invalidator.storage.redis_adapter');
                $container->removeDefinition('contena.cache.invalidator.storage.redis');
                break;
            case 'redis':
                if ($container->getParameter('contena.cache.invalidation.delay_options.connection') === null) {
                    throw AdapterException::missingRequiredParameter('contena.cache.invalidation.delay_options.connection');
                }

                $container->removeDefinition('contena.cache.invalidator.storage.mysql');
                break;
        }
    }
}
