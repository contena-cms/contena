<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Contena\Core\Framework\Framework;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[CoversClass(Framework::class)]
class FrameworkTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $framework = new Framework();

        static::assertSame(-1, $framework->getTemplatePriority());
    }

    public function testFeatureFlagRegisteredOnBoot(): void
    {
        $container = new Container();
        $registry = $this->createMock(FeatureFlagRegistry::class);
        $registry->expects($this->once())->method('register');

        $stampedeProtectionConfigurator = $this->createMock(StampedeProtectionConfigurator::class);
        $stampedeProtectionConfigurator->expects($this->once())->method('apply');

        $container->set(FeatureFlagRegistry::class, $registry);
        $container->set(StampedeProtectionConfigurator::class, $stampedeProtectionConfigurator);
        $container->set(DefinitionInstanceRegistry::class, static::createStub(DefinitionInstanceRegistry::class));
        $container->setParameter('kernel.cache_dir', '/tmp');
        $container->setParameter('contena.cache.compress', true);
        $container->setParameter('contena.cache.compression_method', 'gzip');
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'test');
        $container->compile();

        $framework = new Framework();
        $framework->setContainer($container);

        $framework->boot();
    }
}
