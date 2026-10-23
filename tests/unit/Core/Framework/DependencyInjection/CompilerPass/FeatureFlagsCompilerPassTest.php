<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface;
use Contena\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Contena\Core\Framework\Deprecation\ClassAliasRegistry;
use Contena\Core\Framework\Feature;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(FeatureFlagCompilerPass::class)]
class FeatureFlagsCompilerPassTest extends TestCase
{
    private FeatureFlagCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new FeatureFlagCompilerPass();
    }

    public function testItRejectsFeatureFlagsParameterWithWrongType(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('contena.feature.flags', 'invalid');

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType('contena.feature.flags', 'array', 'string'));
        $this->compilerPass->process($container);
    }

    #[DataProvider('featureTagsRequiringFlag')]
    public function testItRejectsFeatureTagWithoutFlag(string $tag): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('feature_service', (new Definition())->addTag($tag));
        $container->setParameter('contena.feature.flags', []);

        $this->expectExceptionObject(DependencyInjectionException::featureTagMissingFlag('feature_service', $tag));
        $this->compilerPass->process($container);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function featureTagsRequiringFlag(): iterable
    {
        yield 'new service tag' => ['contena.feature'];
        yield 'deprecated service tag' => ['contena.inactiveFeature'];
    }

    public function testItRemovesServiceIfInactive(): void
    {
        $definition = new Definition();
        $definition->addTag('contena.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('contena.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => false,
                'default' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertFalse($container->hasDefinition('feature_service'));
    }

    public function testItKeepServiceIfActive(): void
    {
        $definition = new Definition();
        $definition->addTag('contena.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('contena.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => true,
                'default' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertTrue($container->hasDefinition('feature_service'));
    }

    public function testItRemovesInactiveFeatureFlaggedServiceFromTaggedServices(): void
    {
        $definition = new Definition();
        $definition->addTag('contena.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);
        $definition->addTag('contena.app_lifecycle.persister');

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('contena.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => false,
                'default' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertSame([], $container->findTaggedServiceIds('contena.app_lifecycle.persister'));
    }

    public function testItKeepsActiveFeatureFlaggedServiceInTaggedServices(): void
    {
        $definition = new Definition();
        $definition->addTag('contena.feature', [
            'flag' => 'FEATURE_NEXT_123',
        ]);
        $definition->addTag('contena.app_lifecycle.persister');

        $container = new ContainerBuilder();
        $container->setDefinitions([
            'feature_service' => $definition,
        ]);

        $container->setParameter('contena.feature.flags', [
            'FEATURE_NEXT_123' => [
                'name' => 'FEATURE_NEXT_123',
                'active' => true,
                'default' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $this->compilerPass->process($container);

        static::assertArrayHasKey('feature_service', $container->findTaggedServiceIds('contena.app_lifecycle.persister'));
    }

    public function testItRemovesInactiveFeatureTaggedServiceWhenFlagIsActive(): void
    {
        $definition = new Definition();
        $definition->addTag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

        $container = new ContainerBuilder();
        $container->setDefinition('deprecated_service', $definition);
        $container->setParameter('contena.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => true],
        ]);

        Feature::withFeatureEnabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertFalse($container->hasDefinition('deprecated_service'));
    }

    public function testItKeepsInactiveFeatureTaggedServiceWhenFlagIsInactive(): void
    {
        $definition = new Definition();
        $definition->addTag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

        $container = new ContainerBuilder();
        $container->setDefinition('deprecated_service', $definition);
        $container->setParameter('contena.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => false],
        ]);

        Feature::withFeatureDisabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasDefinition('deprecated_service'));
    }

    public function testItKeepsInactiveFeatureTaggedServiceForPendingMajor(): void
    {
        $definition = new Definition();
        $definition->addTag('contena.inactiveFeature', ['flag' => 'v6.9.0.0']);

        $container = new ContainerBuilder();
        $container->setDefinition('deprecated_service', $definition);
        $container->setParameter('contena.feature.flags', []);

        Feature::fake([], fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasDefinition('deprecated_service'));
    }

    public function testItRemovesListedServiceAliasesWhenFlagIsActive(): void
    {
        $previousClassNames = [
            'Contena\Administration\Controller\NotificationController',
            'Contena\Administration\Notification\NotificationDefinition',
            'Contena\Core\Framework\Plugin\Util\AssetService',
            'Contena\Elasticsearch\Blog\SearchConfigLoader',
        ];

        $container = new ContainerBuilder();
        foreach ($previousClassNames as $previousClassName) {
            $currentClassName = ClassAliasRegistry::ALIASES[$previousClassName];
            $container->setDefinition($currentClassName, new Definition());
            $container->setAlias($previousClassName, $currentClassName);
        }
        $unlistedAlias = 'Contena\Administration\Notification\NotificationCollection';
        $container->setDefinition(ClassAliasRegistry::ALIASES[$unlistedAlias], new Definition());
        $container->setAlias($unlistedAlias, ClassAliasRegistry::ALIASES[$unlistedAlias])
            ->setDeprecated('contena/core', '6.7.0.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('contena.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => true],
        ]);

        Feature::withFeatureEnabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        foreach ($previousClassNames as $previousClassName) {
            static::assertFalse($container->hasAlias($previousClassName));
            static::assertTrue($container->hasDefinition(ClassAliasRegistry::ALIASES[$previousClassName]));
        }
        static::assertTrue($container->hasAlias($unlistedAlias));
    }

    public function testItKeepsMovedClassServiceAliasWhenFlagIsInactive(): void
    {
        $previousClassName = 'Contena\Administration\Controller\NotificationController';
        $currentClassName = ClassAliasRegistry::ALIASES[$previousClassName];

        $container = new ContainerBuilder();
        $container->setDefinition($currentClassName, new Definition());
        $container->setAlias($previousClassName, $currentClassName)
            ->setDeprecated('contena/core', '6.7.0.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('contena.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => false],
        ]);

        Feature::withFeatureDisabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasAlias($previousClassName));
    }

    public function testItKeepsMovedClassServiceAliasForUnregisteredMajor(): void
    {
        $previousClassName = 'Contena\Administration\Controller\NotificationController';
        $currentClassName = ClassAliasRegistry::ALIASES[$previousClassName];

        $container = new ContainerBuilder();
        $container->setDefinition($currentClassName, new Definition());
        $container->setAlias($previousClassName, $currentClassName)
            ->setDeprecated('contena/core', '6.7.0.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('contena.feature.flags', []);

        Feature::fake([], fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasAlias($previousClassName));
    }

    public function testItRemovesDeprecatedBlogStreamInterfaceAliasWhenFlagIsActive(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ContentStreamBuilder::class, new Definition());
        $container->setAlias(ContentStreamBuilderInterface::class, ContentStreamBuilder::class)
            ->setDeprecated('contena/core', '6.8.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('contena.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => true],
        ]);

        Feature::withFeatureEnabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertFalse($container->hasAlias(ContentStreamBuilderInterface::class));
        static::assertTrue($container->hasDefinition(ContentStreamBuilder::class));
    }

    public function testItKeepsDeprecatedBlogStreamInterfaceAliasWhenFlagIsInactive(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ContentStreamBuilder::class, new Definition());
        $container->setAlias(ContentStreamBuilderInterface::class, ContentStreamBuilder::class)
            ->setDeprecated('contena/core', '6.8.0', 'The "%alias_id%" service alias is deprecated.');
        $container->setParameter('contena.feature.flags', [
            'v6.8.0.0' => ['major' => true, 'active' => false],
        ]);

        Feature::withFeatureDisabled('v6.8.0.0', fn () => $this->compilerPass->process($container));

        static::assertTrue($container->hasAlias(ContentStreamBuilderInterface::class));
    }
}
