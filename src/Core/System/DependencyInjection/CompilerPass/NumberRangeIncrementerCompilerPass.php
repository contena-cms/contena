<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection\CompilerPass;

use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class NumberRangeIncrementerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('contena.number_range.config.connection') !== null) {
            return;
        }

        // we remove service from container when required configurations are missing
        // we always keep mysql storage so MigrateIncrementStorageCommand works
        $container->removeDefinition('contena.number_range.redis');
        $container->removeDefinition(IncrementRedisStorage::class);
    }
}
