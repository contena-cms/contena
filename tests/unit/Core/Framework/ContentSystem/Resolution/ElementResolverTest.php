<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Contena\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Contena\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Contena\Core\Framework\ContentSystem\Resolution\ResolutionContext;
use Contena\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Contena\Tests\Unit\Core\Framework\ContentSystem\Fixture\LoaderConfigSpecificationFixture;

/**
 * @internal
 */
#[CoversClass(ElementResolver::class)]
class ElementResolverTest extends TestCase
{
    #[TestDox('resolves a primitive to a static value carrying type, default and required flag, never blocking')]
    public function testResolvesPrimitiveToStaticValue(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true, default: 'Hello')->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertCount(1, $resolutions);
        static::assertSame('headline', $resolutions[0]->key);
        static::assertSame(PropertyKind::Primitive, $resolutions[0]->kind);
        static::assertSame('string', $resolutions[0]->type);
        static::assertSame('Hello', $resolutions[0]->default);
        static::assertTrue($resolutions[0]->required);
        static::assertNull($resolutions[0]->resolved);
        static::assertSame([], $resolutions[0]->candidates);
    }

    #[TestDox('resolves a reference via the single matching ancestor provider, keeping loaders as alternatives')]
    public function testReferenceResolvesViaSingleParent(): void
    {
        $available = [new ProvidedContext(
            contextKey: 'blog',
            fqcn: ChannelBlogEntity::class,
            contextType: ContextType::Single,
            providerElementId: 'root-1',
            distribution: DistributionStrategy::Broadcast,
        )];

        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('blog', BlogEntity::class, required: true)->build(),
            new ResolutionContext('el-1', $available),
            new ContentSystemDataLoaderMap(
                ['entity' => [new LoaderTypeCapability(ChannelBlogEntity::class, ['entity' => 'blog'])]],
                ['entity' => LoaderConfigSpecificationFixture::entityProperty()],
            ),
        );

        static::assertSame(PropertyKind::Reference, $resolutions[0]->kind);
        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Parent, $resolutions[0]->resolved->origin);
        static::assertSame('root-1', $resolutions[0]->resolved->providerElementId);
        static::assertCount(2, $resolutions[0]->candidates);
    }

    #[TestDox('resolves a reference via the single complete loader when no provider is available')]
    public function testReferenceResolvesViaCompleteLoader(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap(
                ['category_fixed' => [new LoaderTypeCapability(CategoryEntity::class)]],
                ['category_fixed' => new LoaderConfigSpecification([])],
            ),
            $this->serializersDecoding(succeeds: true),
        );

        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Loader, $resolutions[0]->resolved->origin);
        static::assertSame('category_fixed', $resolutions[0]->resolved->loaderSource);
        static::assertTrue($resolutions[0]->resolved->configComplete);
    }

    #[TestDox('leaves a reference unresolved with an incomplete candidate when its only loader has required config keys')]
    public function testReferenceWithIncompleteLoaderConfig(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('blog', ChannelBlogEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap(
                ['entity' => [new LoaderTypeCapability(ChannelBlogEntity::class, ['entity' => 'blog'])]],
                ['entity' => LoaderConfigSpecificationFixture::entityProperty()],
            ),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(1, $resolutions[0]->candidates);
        static::assertSame(CandidateOrigin::Loader, $resolutions[0]->candidates[0]->origin);
        static::assertFalse($resolutions[0]->candidates[0]->configComplete);
        static::assertSame(['entity' => 'blog'], $resolutions[0]->candidates[0]->configTemplate);
    }

    #[TestDox('leaves a reference unresolved and lists every candidate when multiple complete loaders match')]
    public function testReferenceWithMultipleSourcesIsAmbiguous(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap(
                [
                    'category_a' => [new LoaderTypeCapability(CategoryEntity::class)],
                    'category_b' => [new LoaderTypeCapability(CategoryEntity::class)],
                ],
                [
                    'category_a' => new LoaderConfigSpecification([]),
                    'category_b' => new LoaderConfigSpecification([]),
                ],
            ),
            $this->serializersDecoding(succeeds: true),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(2, $resolutions[0]->candidates);
    }

    #[TestDox('leaves a reference unresolved with no candidates when neither provider nor loader matches')]
    public function testReferenceWithNoSource(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('blog', BlogEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderMap([], []),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertSame([], $resolutions[0]->candidates);
    }

    #[TestDox('yields no resolutions for an unregistered element type, leaving the defect to the diagnostics layer')]
    public function testUnregisteredTypeYieldsNoResolutions(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);

        $resolver = new ElementResolver(
            $registry,
            $this->typeResolver(new ContentSystemDataLoaderMap([], [])),
            static::createStub(DataLoaderConfigSerializerProvider::class),
            static::createStub(DataLoaderProvider::class),
        );

        static::assertSame([], $resolver->resolve('CT:Unknown', new ResolutionContext('el-1', [])));
    }

    #[TestDox('resolves a required reference to the applied Stored candidate over a deterministic environment default, leaving the environment candidates list unchanged')]
    public function testAppliedStoredWiringTakesPrecedenceOverEnvironmentDefault(): void
    {
        $available = [new ProvidedContext(
            contextKey: 'blog',
            fqcn: ChannelBlogEntity::class,
            contextType: ContextType::Single,
            providerElementId: 'root-1',
            distribution: DistributionStrategy::Broadcast,
        )];

        $element = ContentElementBuilder::create('CT:Block', 'el-1')
            ->withDataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(ChannelBlogEntity::class);

        $resolver = $this->appliedWiringResolver($loader);

        $resolutions = $resolver->resolve($element, new ResolutionContext('el-1', $available));

        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Stored, $resolutions[0]->resolved->origin);
        static::assertCount(1, $resolutions[0]->candidates);
        static::assertSame(CandidateOrigin::Parent, $resolutions[0]->candidates[0]->origin);
        static::assertSame([], array_values(array_filter(
            $resolutions[0]->candidates,
            static fn (ResolutionCandidate $candidate): bool => $candidate->origin === CandidateOrigin::Stored,
        )));
    }

    #[TestDox('falls back to the environment default when applied wiring produces a type not assignable to the declared reference')]
    public function testTypeMismatchedAppliedWiringFallsBackToEnvironmentDefault(): void
    {
        $available = [new ProvidedContext(
            contextKey: 'blog',
            fqcn: ChannelBlogEntity::class,
            contextType: ContextType::Single,
            providerElementId: 'root-1',
            distribution: DistributionStrategy::Broadcast,
        )];

        $element = ContentElementBuilder::create('CT:Block', 'el-1')
            ->withDataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(CategoryEntity::class);

        $resolver = $this->appliedWiringResolver($loader);

        $resolutions = $resolver->resolve($element, new ResolutionContext('el-1', $available));

        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateOrigin::Parent, $resolutions[0]->resolved->origin);
    }

    #[TestDox('yields no Stored resolution when applied wiring resolution throws a client-defect exception')]
    public function testClientDefectDuringAppliedWiringYieldsNoStoredResolution(): void
    {
        $element = ContentElementBuilder::create('CT:Block', 'el-1')
            ->withDataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::configSerializerNotRegistered('entity'));

        $resolver = $this->appliedWiringResolver($loader);

        $resolutions = $resolver->resolve($element, new ResolutionContext('el-1', []));

        static::assertNull($resolutions[0]->resolved);
    }

    #[TestDox('propagates a non-client-defect exception raised while resolving applied wiring\'s produced type')]
    public function testNonClientDefectDuringAppliedWiringPropagates(): void
    {
        $element = ContentElementBuilder::create('CT:Block', 'el-1')
            ->withDataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $exception = ContentSystemException::mutationTargetNotFound('el-1');

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException($exception);

        $resolver = $this->appliedWiringResolver($loader);

        $this->expectExceptionObject($exception);

        $resolver->resolve($element, new ResolutionContext('el-1', []));
    }

    /**
     * @return list<PropertyResolution>
     */
    private function resolve(
        ContentSystemElementTypeSpecification $spec,
        ResolutionContext $context,
        ContentSystemDataLoaderMap $map,
        ?DataLoaderConfigSerializerProvider $serializers = null,
    ): array {
        $resolver = new ElementResolver(
            $this->registryReturning($spec),
            $this->typeResolver($map),
            $serializers ?? static::createStub(DataLoaderConfigSerializerProvider::class),
            static::createStub(DataLoaderProvider::class),
        );

        return $resolver->resolve('CT:Block', $context);
    }

    private function registryReturning(ContentSystemElementTypeSpecification $spec): AbstractContentSystemElementTypeRegistry
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($spec);

        return $registry;
    }

    private function typeResolver(ContentSystemDataLoaderMap $map): AbstractContentSystemDataLoaderMapResolver
    {
        $resolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $resolver->method('resolve')->willReturn($map);

        return $resolver;
    }

    /**
     * @param AbstractContentDataLoader<Struct> $loader
     */
    private function loaderProvider(AbstractContentDataLoader $loader): DataLoaderProvider
    {
        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        return $provider;
    }

    /**
     * @param AbstractContentDataLoader<Struct> $loader
     */
    private function appliedWiringResolver(AbstractContentDataLoader $loader): ElementResolver
    {
        return new ElementResolver(
            $this->registryReturning(ContentSystemElementTypeSpecificationBuilder::create()->reference('blog', ChannelBlogEntity::class, required: true)->build()),
            $this->typeResolver(new ContentSystemDataLoaderMap([], [])),
            static::createStub(DataLoaderConfigSerializerProvider::class),
            $this->loaderProvider($loader),
        );
    }

    private function serializersDecoding(bool $succeeds): DataLoaderConfigSerializerProvider
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);

        if ($succeeds) {
            $serializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

            return $serializers;
        }

        $serializers->method('decode')->willThrowException(ContentSystemException::configSerializerNotRegistered('x'));

        return $serializers;
    }
}
