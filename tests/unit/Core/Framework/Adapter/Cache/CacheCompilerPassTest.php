<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\AdapterException;
use Contena\Core\Framework\Adapter\Cache\CacheCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(CacheCompilerPass::class)]
class CacheCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->addDefinitions([
            'contena.cache.invalidator.storage.redis_adapter' => new Definition(),
            'contena.cache.invalidator.storage.redis' => new Definition(),
            'contena.cache.invalidator.storage.mysql' => new Definition(),
        ]);
        $this->container->setParameter('contena.number_range.config.connection', null);
    }

    public function testProcessMySQL(): void
    {
        $container = $this->container;
        $container->setParameter('contena.cache.invalidation.delay_options.storage', 'mysql');

        $compilerPass = new CacheCompilerPass();
        $compilerPass->process($container);

        static::assertFalse($container->hasDefinition('contena.cache.invalidator.storage.redis'));
        static::assertFalse($container->hasDefinition('contena.cache.invalidator.storage.redis_adapter'));
        static::assertTrue($container->hasDefinition('contena.cache.invalidator.storage.mysql'));
    }

    public function testProcessRedis(): void
    {
        $container = $this->container;
        $container->setParameter('contena.cache.invalidation.delay_options.storage', 'redis');
        $container->setParameter('contena.cache.invalidation.delay_options.connection', 'connection_name');

        $compilerPass = new CacheCompilerPass();
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition('contena.cache.invalidator.storage.redis'));
        static::assertTrue($container->hasDefinition('contena.cache.invalidator.storage.redis_adapter'));
        static::assertFalse($container->hasDefinition('contena.cache.invalidator.storage.mysql'));
    }

    public function testProcessRedisNoConnectionConfigured(): void
    {
        $container = $this->container;
        $container->setParameter('contena.cache.invalidation.delay_options.storage', 'redis');
        $container->setParameter('contena.cache.invalidation.delay_options.connection', null); // default value

        self::expectExceptionObject(AdapterException::missingRequiredParameter('contena.cache.invalidation.delay_options.connection'));
        $compilerPass = new CacheCompilerPass();
        $compilerPass->process($container);
    }
}
