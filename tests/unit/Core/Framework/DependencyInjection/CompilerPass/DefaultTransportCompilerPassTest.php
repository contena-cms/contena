<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(DefaultTransportCompilerPass::class)]
class DefaultTransportCompilerPassTest extends TestCase
{
    public function testAliasIsRegistered(): void
    {
        $container = new ContainerBuilder();

        $container->setParameter('messenger.default_transport_name', 'test');
        $container->setParameter('kernel.debug', true);

        $container->addCompilerPass(new DefaultTransportCompilerPass());

        // disable removing passes because the alias will not be used
        $container->getCompilerPassConfig()->setRemovingPasses([]);

        $container->compile(true);

        static::assertSame('messenger.transport.test', (string) $container->getAlias('messenger.default_transport'));
    }
}
