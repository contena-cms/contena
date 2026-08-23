<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\RateLimiter\RateLimiterFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimiterCompilerPass implements CompilerPassInterface
{
    private const bool DEFAULT_ENABLED_STATE = true;

    private const string DEFAULT_CACHE_POOL = 'cache.rate_limiter';

    private const string DEFAULT_LOCK_FACTORY = 'lock.factory';

    public function process(ContainerBuilder $container): void
    {
        $rateLimiter = $container->getDefinition(RateLimiter::class);

        $rateLimiterConfig = $container->getParameter('contena.api.rate_limiter');

        foreach ($rateLimiterConfig as $name => $config) {
            $this->setConfigDefaults($config);

            $def = new Definition(RateLimiterFactory::class);
            $def->addArgument($config + ['id' => $name]); // config

            $cacheDef = new Definition(CacheStorage::class);
            $cacheDef->addArgument(new Reference($config['cache_pool']));

            $def->addArgument($cacheDef);
            $def->addArgument(new Reference(SystemConfigService::class));
            $def->addArgument(new Reference(ClockInterface::class));
            $def->addArgument(new Reference($config['lock_factory']));

            $rateLimiter->addMethodCall('registerLimiterFactory', [$name, $def]);
        }

        $container->setDefinition('contena.rate_limiter', $rateLimiter);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function setConfigDefaults(array &$config): void
    {
        if (!\array_key_exists('enabled', $config)) {
            $config['enabled'] = self::DEFAULT_ENABLED_STATE;
        }

        if (!\array_key_exists('cache_pool', $config)) {
            $config['cache_pool'] = self::DEFAULT_CACHE_POOL;
        }

        if (!\array_key_exists('lock_factory', $config)) {
            $config['lock_factory'] = self::DEFAULT_LOCK_FACTORY;
        }
    }
}
