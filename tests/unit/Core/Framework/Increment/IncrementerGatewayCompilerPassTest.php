<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Increment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Increment\AbstractIncrementer;
use Contena\Core\Framework\Increment\ArrayIncrementer;
use Contena\Core\Framework\Increment\IncrementerGatewayCompilerPass;
use Contena\Core\Framework\Increment\MySQLIncrementer;
use Contena\Core\Framework\Increment\RedisIncrementer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(IncrementerGatewayCompilerPass::class)]
class IncrementerGatewayCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('contena.increment', [
            'user_activity' => [
                'type' => 'mysql',
            ],
            'message_queue' => [
                'type' => 'redis',
                'config' => ['connection' => 'redis_incrementer'],
            ],
            'another_pool' => [
                'type' => 'array',
            ],
        ]);

        $container->register('contena.increment.gateway.array', ArrayIncrementer::class)
            ->addArgument('');

        $container->register('contena.increment.gateway.mysql', MySQLIncrementer::class)
            ->addArgument('')
            ->addArgument(static::createStub(Connection::class));

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);

        // user_activity pool is registered
        static::assertTrue($container->hasDefinition('contena.increment.user_activity.gateway.mysql'));
        $definition = $container->getDefinition('contena.increment.user_activity.gateway.mysql');
        static::assertSame(MySQLIncrementer::class, $definition->getClass());
        static::assertTrue($definition->hasTag('contena.increment.gateway'));

        // message_queue pool is registered
        static::assertTrue($container->hasDefinition('contena.increment.message_queue.redis_adapter'));
        static::assertTrue($container->hasDefinition('contena.increment.message_queue.gateway.redis'));
        $definition = $container->getDefinition('contena.increment.message_queue.gateway.redis');
        static::assertSame(RedisIncrementer::class, $definition->getClass());
        static::assertTrue($definition->hasTag('contena.increment.gateway'));

        // another_pool is registered
        static::assertNotNull($container->hasDefinition('contena.increment.message_queue.gateway.redis'));
        $definition = $container->getDefinition('contena.increment.message_queue.gateway.redis');
        static::assertSame(RedisIncrementer::class, $definition->getClass());
        static::assertTrue($definition->hasTag('contena.increment.gateway'));
    }

    public function testCustomPoolGateway(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('contena.increment', ['custom_pool' => ['type' => 'custom_type']]);

        $customGateway = new class extends AbstractIncrementer {
            public function decrement(string $cluster, string $key): void
            {
            }

            public function increment(string $cluster, string $key): void
            {
            }

            /**
             * @return array<string, array<string, mixed>>
             */
            public function list(string $cluster, int $limit = 5, int $offset = 0): array
            {
                return [];
            }

            public function reset(string $cluster, ?string $key = null): void
            {
            }

            public function delete(string $cluster, array $keys = []): void
            {
            }

            public function getPool(): string
            {
                return 'custom-pool';
            }
        };

        $container->setDefinition('contena.increment.custom_pool.gateway.custom_type', new Definition($customGateway::class));

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);

        // custom_pool pool is registered
        static::assertTrue($container->hasDefinition('contena.increment.custom_pool.gateway.custom_type'));
        $definition = $container->getDefinition('contena.increment.custom_pool.gateway.custom_type');
        static::assertSame($customGateway::class, $definition->getClass());
        static::assertTrue($definition->hasTag('contena.increment.gateway'));
    }

    public function testInvalidCustomPoolGateway(): void
    {
        static::expectException(\RuntimeException::class);
        $container = new ContainerBuilder();
        $container->setParameter('contena.increment', ['custom_pool' => []]);
        $container->setParameter('contena.increment.custom_pool.type', 'custom_type');

        $customGateway = new class {
            public function getPool(): string
            {
                return 'custom-pool';
            }
        };

        $container->setDefinition('contena.increment.custom_pool.gateway.custom_type', new Definition($customGateway::class));

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);

        // custom_pool pool is registered
        static::assertTrue($container->hasDefinition('contena.increment.custom_pool.gateway.custom_type'));
        $definition = $container->getDefinition('contena.increment.custom_pool.gateway.custom_type');
        static::assertSame($customGateway::class, $definition->getClass());
        static::assertTrue($definition->hasTag('contena.increment.gateway'));
    }

    public function testInvalidType(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Can not find increment gateway for configured type foo of pool custom_pool, expected service id contena.increment.custom_pool.gateway.foo can not be found'));
        $container = new ContainerBuilder();
        $container->setParameter('contena.increment', ['custom_pool' => [
            'type' => 'foo',
        ]]);
        $container->setParameter('contena.increment.custom_pool.type', 'invalid');

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);
    }

    public function testInvalidAdapterClass(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Increment gateway with id contena.increment.custom_pool.gateway.array, expected service instance of Contena\Core\Framework\Increment\AbstractIncrementer'));
        $container = new ContainerBuilder();
        $container->setParameter('contena.increment', ['custom_pool' => ['type' => 'array']]);
        $container->setParameter('contena.increment.custom_pool.type', 'custom_type');
        $container->setDefinition('contena.increment.gateway.array', new Definition(\ArrayObject::class));

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);
    }

    public function testInvalidRedisAdapter(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Can not find increment gateway for configured type redis of pool custom_pool, expected service id contena.increment.custom_pool.gateway.redis can not be found'));

        $container = new ContainerBuilder();
        $container->setParameter('contena.increment', ['custom_pool' => ['type' => 'redis']]);
        $container->setParameter('contena.increment.custom_pool.type', 'custom_type');

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);
    }
}
