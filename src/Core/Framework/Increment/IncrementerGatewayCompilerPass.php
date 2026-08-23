<?php declare(strict_types=1);

namespace Contena\Core\Framework\Increment;

use Contena\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
class IncrementerGatewayCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $services = $container->getParameter('contena.increment');
        $tag = 'contena.increment.gateway';

        foreach ($services as $pool => $service) {
            $type = $service['type'] ?? null;

            if (!\is_string($type)) {
                throw IncrementException::wrongGatewayType($pool);
            }

            $active = \sprintf('contena.increment.%s.gateway.%s', $pool, $type);
            $config = [];

            // If service is not registered directly in the container, try to resolve them using fallback gateway
            if (!$container->hasDefinition($active)) {
                if (\array_key_exists('config', $service)) {
                    $config = $service['config'];
                }

                $active = $this->resolveTypeDefinition($container, $pool, $type, $config);
            }

            if (!$container->hasDefinition($active)) {
                throw IncrementException::gatewayServiceNotFound($type, $pool, $active);
            }

            $definition = $container->getDefinition($active);

            if (!$definition->hasTag($tag)) {
                $definition->addTag($tag);
            }

            $class = $definition->getClass();

            if ($class === null || !is_subclass_of($class, AbstractIncrementer::class)) {
                throw IncrementException::wrongGatewayClass($active, AbstractIncrementer::class);
            }

            $definition->addMethodCall('setPool', [$pool]);
            $definition->addMethodCall('setConfig', [$config]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveTypeDefinition(ContainerBuilder $container, string $pool, string $type, array $config = []): string
    {
        // contena.increment.gateway.mysql is fallback gateway if custom gateway is not set
        $fallback = \sprintf('contena.increment.gateway.%s', $type);

        $gatewayServiceName = \sprintf('contena.increment.%s.gateway.%s', $pool, $type);

        switch ($type) {
            case 'array':
            case 'mysql':
                $referenceDefinition = $container->getDefinition($fallback);

                $definition = new Definition($referenceDefinition->getClass());
                $definition->setArguments($referenceDefinition->getArguments());
                $definition->setTags($referenceDefinition->getTags());

                $container->setDefinition($gatewayServiceName, $definition);

                return $gatewayServiceName;
            case 'redis':
                $connectionDefinition = new Definition('Redis');

                if (\array_key_exists('connection', $config)) {
                    $connectionDefinition->setFactory([new Reference(RedisConnectionProvider::class), 'getConnection'])->addArgument($config['connection']);
                } else {
                    return $gatewayServiceName;
                }

                $adapterServiceName = \sprintf('contena.increment.%s.redis_adapter', $pool);

                $container->setDefinition($adapterServiceName, $connectionDefinition);

                $definition = new Definition(RedisIncrementer::class);
                $definition->addArgument(new Reference($adapterServiceName));

                $container->setDefinition($gatewayServiceName, $definition);

                return $gatewayServiceName;

            default:
                return $gatewayServiceName;
        }
    }
}
