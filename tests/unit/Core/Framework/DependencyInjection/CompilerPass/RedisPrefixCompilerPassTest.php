<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\CompilerPass\RedisPrefixCompilerPass;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(RedisPrefixCompilerPass::class)]
class RedisPrefixCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();

        $definition = new Definition(RedisTagAwareAdapter::class);
        $definition->setArguments(['', 'foo']);

        $container->setDefinition('foo', $definition);

        $pass = new RedisPrefixCompilerPass();
        $pass->process($container);

        static::assertSame('%contena.cache.redis_prefix%foo', $definition->getArgument(1));
    }
}
