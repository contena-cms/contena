<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Contena\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\McpToolResultCacheCompilerPass;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Contena\Core\Framework\Framework;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Contena\Core\Framework\Test\DependencyInjection\CompilerPass\ContainerVisibilityCompilerPass;
use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

    public function testBuild(): void
    {
        $container = $this->buildContainer('prod');

        static::assertSame('en-GB', $container->getParameter('locale'));
        static::assertTrue($container->hasDefinition(HtmlSanitizer::class));
        static::assertTrue($container->hasDefinition(CacheClearer::class));

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        static::assertNotCount(0, array_filter($passes, static fn ($pass) => $pass instanceof FeatureFlagCompilerPass));
        static::assertNotCount(0, array_filter($passes, static fn ($pass) => $pass instanceof EntityCompilerPass));
        static::assertNotCount(0, array_filter($passes, static fn ($pass) => $pass instanceof McpToolDiscoveryCompilerPass));
        static::assertNotCount(0, array_filter($passes, static fn ($pass) => $pass instanceof McpToolResultCacheCompilerPass), 'only this pass wires the tool-result cache into MCP tools');

        static::assertFalse($container->hasDefinition(AclTestController::class));
        static::assertCount(0, array_filter($passes, static fn ($pass) => $pass instanceof DisableRateLimiterCompilerPass));
    }

    public function testBuildRegistersTestServicesInTestEnvironment(): void
    {
        $container = $this->buildContainer('test');

        static::assertTrue($container->hasDefinition(AclTestController::class));

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        static::assertNotCount(0, array_filter($passes, static fn ($pass) => $pass instanceof DisableRateLimiterCompilerPass));
        static::assertNotCount(0, array_filter($passes, static fn ($pass) => $pass instanceof ContainerVisibilityCompilerPass));
    }

    #[TestDox('boot registers the feature flags and applies the runtime configuration')]
    #[DataProvider('bootConfigurationProvider')]
    public function testBootAppliesRuntimeConfiguration(bool $compress, string $compressMethod, bool $debug): void
    {
        $container = new Container();
        $registry = $this->createMock(FeatureFlagRegistry::class);
        $registry->expects($this->once())->method('register');

        $stampedeProtectionConfigurator = $this->createMock(StampedeProtectionConfigurator::class);
        $stampedeProtectionConfigurator->expects($this->once())->method('apply');

        $container->set(FeatureFlagRegistry::class, $registry);
        $container->set(StampedeProtectionConfigurator::class, $stampedeProtectionConfigurator);
        $container->set(ReplicaConnectionResetter::class, new ReplicaConnectionResetter(static::createStub(Connection::class)));
        $container->set(DefinitionInstanceRegistry::class, static::createStub(DefinitionInstanceRegistry::class));
        $container->set(ChannelDefinitionInstanceRegistry::class, static::createStub(ChannelDefinitionInstanceRegistry::class));
        $container->setParameter('kernel.cache_dir', '/tmp');
        $container->setParameter('contena.cache.compress', $compress);
        $container->setParameter('contena.cache.compression_method', $compressMethod);
        $container->setParameter('kernel.debug', $debug);
        $container->setParameter('kernel.environment', 'test');
        $container->compile();

        $framework = new Framework();
        $framework->setContainer($container);

        // boot() assigns process-global statics: restore them so no other test inherits this run
        $before = [CacheValueCompressor::$compress, CacheValueCompressor::$compressMethod, Feature::$emitDeprecations];

        try {
            $framework->boot();

            static::assertSame($compress, CacheValueCompressor::$compress);
            static::assertSame($compressMethod, CacheValueCompressor::$compressMethod);
            static::assertSame($debug, Feature::$emitDeprecations);
        } finally {
            [CacheValueCompressor::$compress, CacheValueCompressor::$compressMethod, Feature::$emitDeprecations] = $before;
        }
    }

    public static function bootConfigurationProvider(): \Generator
    {
        yield 'debug with gzip compression' => [true, 'gzip', true];
        yield 'no debug, no compression, zstd configured' => [false, 'zstd', false];
    }

    private function buildContainer(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        // buildDefaultConfig() loads Resources/config/packages/*, whose root
        // keys require the extensions of these bundles to be registered
        foreach ([new FrameworkBundle(), new TwigBundle(), new MonologBundle()] as $bundle) {
            $extension = $bundle->getContainerExtension();
            static::assertNotNull($extension);
            $container->registerExtension($extension);
        }

        $framework = new Framework();
        $container->registerExtension($framework->getContainerExtension());
        $framework->build($container);

        return $container;
    }
}
