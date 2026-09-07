<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Contena\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Contena\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Contena\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Contena\Core\Framework\ContentSystem\Rendering\ContextDistributor;
use Contena\Core\Framework\ContentSystem\Rendering\ElementDataResolver;
use Contena\Core\Framework\ContentSystem\Rendering\ElementLowering;
use Contena\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Contena\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Contena\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Contena\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfigSerializer;
use Contena\Core\Test\Stub\ContentSystem\StubStruct;
use Contena\Core\Test\Stub\ContentSystem\TestNavigationShapedLoaderConfig;
use Contena\Core\Test\Stub\ContentSystem\TestNavigationShapedLoaderConfigSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The collaborator chain is real below the loader seam: a real `ElementDataResolver` over a real
 * `LoaderInputResolver`, a real `ContextDeliveryResolver` over a real `ContextDistributor`, and a real
 * `RenderedTreeFactory` over a real `RenderedElementFactory`. Only the data loader and the element type
 * registry are doubles, so what the rendered tree carries is what the three layers actually produced.
 *
 * @internal
 */
#[CoversClass(ElementLowering::class)]
class ElementLoweringTest extends TestCase
{
    /**
     * An effort-negative claim: the never-expectation is the behaviour under test, not decoration. A skeleton
     * is a structural answer, so neither the element's declared data requirement nor the page-level one may
     * reach a loader, and the rendered properties must stay empty rather than being filled from anywhere. The
     * page-level requirement and the wrapper are supplied in full precisely so the ambient path has
     * everything it needs and still must not run. The two cache facts are whole-value assertions because
     * absence is the claim: a run would disable the context on an uncacheable result and would leave a tag
     * behind, so either fact appearing proves a loader ran.
     */
    #[TestDox('renders structure with empty properties and leaves the cache context untouched in skeleton mode without running any loader')]
    public function testSkeletonModeRendersStructureWithoutRunningAnyLoader(): void
    {
        $loader = $this->loader();
        $loader->expects($this->never())->method('load');
        $cacheContext = new RenderingCacheContext();

        $lowered = $this->lowering($loader)->lower(
            [$this->rootOverRequiringChild()],
            RenderingMode::SKELETON,
            static::createStub(ChannelContext::class),
            new Request(),
            $cacheContext,
            [new DataRequirement('language', 'entity', new StubLoaderConfig())],
            $this->virtualRoot(),
        );

        $tree = $lowered->tree;

        // A skeleton mints no properties, so it records no provenance either: there is nothing to file.
        static::assertSame([], $lowered->provenance);
        static::assertSame('root-1', $tree[0]->id);
        static::assertSame('CT:Section', $tree[0]->component);
        static::assertSame([], $tree[0]->properties);
        static::assertSame(['main'], array_keys($tree[0]->slots));
        static::assertSame('child-1', $tree[0]->slots['main'][0]->id);
        static::assertSame('CT:Blog', $tree[0]->slots['main'][0]->component);
        static::assertSame([], $tree[0]->slots['main'][0]->properties);
        static::assertFalse($cacheContext->isDisabled());
        static::assertSame([], $cacheContext->getTags());
    }

    /**
     * The data walk descends: the requirement sits two levels below the root, so a walk that only visits the
     * roots resolves nothing and the grandchild renders an empty property map.
     */
    #[TestDox('resolves a data requirement held by an element below the roots in full mode')]
    public function testFullModeResolvesDataForAnElementBelowTheRoots(): void
    {
        $loaded = new StubStruct();
        $grandchild = StoredElementBuilder::create('CT:Blog', 'grandchild-1')
            ->withDataRequirement('blog', 'entity', new StubLoaderConfig())
            ->build();
        $child = StoredElementBuilder::create('CT:Section', 'child-1')
            ->withSlot('inner', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('CT:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();

        $tree = $this->lower($this->loaderReturning(ContentDataLoaderResult::cached($loaded)), [$root]);

        static::assertSame(
            ['blog' => $loaded],
            $tree[0]->slots['main'][0]->slots['inner'][0]->properties
        );
    }

    /**
     * The ordering claim: loading completes over the whole forest before any distribution starts. The parent
     * stores no `blog` of its own, so the only thing that can reach the child is the value the parent's
     * loader produced — and it is compared by identity, so no stored value could stand in for it. Compute the
     * deliveries before the loads and the parent distributes nothing, leaving the child's map empty.
     */
    #[TestDox('delivers a parent loader resolved value to its consuming child in full mode')]
    public function testFullModeDeliversAParentLoadedValueToItsConsumingChild(): void
    {
        $loaded = new StubStruct();
        $child = StoredElementBuilder::create('CT:Box', 'child-1')
            ->withConsumer('blog', ContextType::Single)
            ->build();
        $parent = StoredElementBuilder::create('CT:Section', 'parent-1')
            ->withDataRequirement('blog', 'entity', new StubLoaderConfig())
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $tree = $this->lower($this->loaderReturning(ContentDataLoaderResult::cached($loaded)), [$parent]);

        static::assertSame(['blog' => $loaded], $tree[0]->slots['main'][0]->properties);
    }

    /**
     * A required consumer with a dot path pulls a nested property off the value it is delivered, which needs
     * a Struct to traverse. A provider that hands over a bare scalar leaves the path unresolvable, and a
     * required consumer must fail rather than silently deliver null.
     */
    #[TestDox('throws when a required consumer resolves a dot path into a provided value that is not a struct')]
    public function testFullModeThrowsWhenRequiredConsumerPathIsUnresolvable(): void
    {
        $child = StoredElementBuilder::create('CT:Box', 'child-1')
            ->withConsumer('blog.manufacturer', ContextType::Single, required: true)
            ->build();
        $parent = StoredElementBuilder::create('CT:Section', 'parent-1')
            ->withProperty('blog', 'Release notes')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'blog.manufacturer',
            'child-1',
            'Context data is not a Struct instance'
        ));

        $this->lower($this->loader(), [$parent]);
    }

    /**
     * The ambient path end to end at this seam: the page-level requirement belongs to no element, and the
     * consumer that receives it is three nodes below the wrapper with nothing wired in between. The
     * once-expectation is part of the claim rather than decoration — the delivered value alone cannot tell a
     * single ambient load from one ambient load plus a second run somewhere else.
     */
    #[TestDox('resolves a page-level data requirement exactly once and delivers it to a root-scoped consumer below')]
    public function testFullModeDeliversAPageLevelValueToARootScopedConsumer(): void
    {
        $pageData = new StubStruct();
        $loader = $this->loader();
        $loader->expects($this->once())
            ->method('load')
            ->willReturn(ContentDataLoaderResult::cached($pageData));

        $consumer = StoredElementBuilder::create('CT:Box', 'child-1')
            ->withConsumer('language', ContextType::Single, scope: ConsumerScope::Root)
            ->build();
        $root = StoredElementBuilder::create('CT:Section', 'root-1')
            ->withSlot('main', [$consumer])
            ->build();
        $wrapper = $this->virtualRoot($root);

        // Fixture guard: nothing in the forest declares a data requirement or a provider, so the one load
        // and the one delivered value can only have come through the page-level requirement.
        static::assertSame([], $consumer->dataRequirements);
        static::assertSame([], $root->contextDefinitions->getAllProviders());
        static::assertSame([], $wrapper->dataRequirements);
        static::assertSame([], $wrapper->contextDefinitions->getAllProviders());

        $tree = $this->lower(
            $loader,
            [$wrapper],
            [new DataRequirement('language', 'entity', new StubLoaderConfig())],
            $wrapper
        );

        $delivered = $tree[0]->slots['__page_roots__'][0]->slots['main'][0];
        static::assertSame(['language' => $pageData], $delivered->properties);
        // The wrapper renders none of it: it declares no data requirement, so the ambient values filed under
        // its id reach the mint through no tier at all.
        static::assertSame([], $tree[0]->properties);
    }

    /**
     * The no-wrapper arm. A preparation subscriber can leave a forest the preparation refuses to wrap while
     * the specification still carries page-level requirements, and that combination must resolve nothing
     * rather than reaching for an element to dereference inputs against.
     */
    #[TestDox('runs no page-level loader when the preparation minted no wrapper')]
    public function testFullModeSkipsTheAmbientRunWithoutAWrapper(): void
    {
        $loader = $this->loader();
        $loader->expects($this->never())->method('load');

        $root = StoredElementBuilder::create('CT:Section', 'root-1')
            ->withSlot('main', [
                StoredElementBuilder::create('CT:Box', 'child-1')
                    ->withConsumer('language', ContextType::Single, scope: ConsumerScope::Root)
                    ->build(),
            ])
            ->build();

        $tree = $this->lower(
            $loader,
            [$root],
            [new DataRequirement('language', 'entity', new StubLoaderConfig())],
            null
        );

        static::assertSame([], $tree[0]->slots['main'][0]->properties);
    }

    /**
     * Why the wrapper is the input source rather than an arbitrary element: a page-level requirement's
     * `propertyReference` input names a stored key, and the keys it can name are the placeholder values the
     * wrapper carries. The loader receives the placeholder's VALUE, so a run that dereferenced against
     * anything else would hand it null.
     */
    #[TestDox('dereferences a page-level property reference input against the wrapper placeholder values')]
    public function testAmbientPropertyReferenceInputResolvesFromThePlaceholderValues(): void
    {
        $captured = null;
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false),
        ]));
        $loader->method('load')->willReturnCallback(
            static function (LoaderInputs $inputs) use (&$captured): ContentDataLoaderResult {
                $captured = $inputs;

                return ContentDataLoaderResult::cached(new StubStruct());
            }
        );

        $wrapper = $this->virtualRoot(StoredElementBuilder::create('CT:Section', 'root-1')->build());

        // Fixture guard: the placeholder key the config references really is on the wrapper, and its value
        // is the one the assertion below expects to arrive at the loader.
        static::assertSame('placeholder-blog', $wrapper->property('blogId')?->asString());

        $this->lowering($loader, new TestNavigationShapedLoaderConfigSerializer())->lower(
            [$wrapper],
            RenderingMode::FULL,
            static::createStub(ChannelContext::class),
            new Request(),
            new RenderingCacheContext(),
            [new DataRequirement(
                'blog',
                'entity',
                new TestNavigationShapedLoaderConfig(entity: 'blog', activeProperty: 'blogId')
            )],
            $wrapper
        );

        static::assertInstanceOf(LoaderInputs::class, $captured);
        static::assertSame('placeholder-blog', $captured->get('activeProperty'));
    }

    /**
     * The page-level requirements are what the pipeline supplies from the rendering specification, and the
     * wrapper is the element their loader inputs dereference against. A run with no page-level data passes
     * the empty list and no wrapper, which is what a layout without page-level requirements produces.
     *
     * @param AbstractContentDataLoader<Struct>&Stub $loader
     * @param list<StoredElement> $forest
     * @param list<DataRequirement> $pageDataRequirements
     *
     * @return list<RenderedElement>
     */
    private function lower(
        AbstractContentDataLoader&Stub $loader,
        array $forest,
        array $pageDataRequirements = [],
        ?StoredElement $virtualRoot = null,
    ): array {
        return $this->lowering($loader)->lower(
            $forest,
            RenderingMode::FULL,
            static::createStub(ChannelContext::class),
            new Request(),
            new RenderingCacheContext(),
            $pageDataRequirements,
            $virtualRoot,
        )->tree;
    }

    /**
     * The wrapper as {@see VirtualRootWrapper::wrap()} mints it after this change: the placeholder values as
     * stored properties, no data requirements of its own, no context definitions. It is built here rather
     * than through the wrapper so this test stays about the lowering.
     */
    private function virtualRoot(StoredElement ...$roots): StoredElement
    {
        return new StoredElement(
            VirtualRootWrapper::VIRTUAL_ROOT_ID,
            'CT:Internal:PageContext',
            [],
            ['blogId' => StoredValue::ofString('placeholder-blog')],
            ['__page_roots__' => array_values($roots)],
        );
    }

    /**
     * A root over a child that declares two data requirements, so a loader run in skeleton mode would be
     * visible both as a `load()` call and as a contribution to the cache context.
     */
    private function rootOverRequiringChild(): StoredElement
    {
        $child = StoredElementBuilder::create('CT:Blog', 'child-1')
            ->withDataRequirement('blog', 'entity', new StubLoaderConfig())
            ->withDataRequirement('category', 'entity', new StubLoaderConfig())
            ->build();

        return StoredElementBuilder::create('CT:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();
    }

    /**
     * The config serializer is a parameter because the identity factory hashes each requirement's config
     * through it, so a fixture carrying a differently shaped config needs the serializer that speaks it.
     *
     * @param AbstractContentDataLoader<Struct>&Stub $loader
     */
    private function lowering(
        AbstractContentDataLoader&Stub $loader,
        ?AbstractContentDataLoaderConfigSerializer $configSerializer = null,
    ): ElementLowering {
        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);
        $configSerializer ??= new StubLoaderConfigSerializer();

        return new ElementLowering(
            new ElementDataResolver(
                $provider,
                new LoaderInputResolver(),
                new LoaderValueIdentityFactory(
                    new DataLoaderConfigSerializerProvider(new ServiceLocator([
                        'entity' => static fn (): AbstractContentDataLoaderConfigSerializer => $configSerializer,
                    ])),
                    new ConfigCanonicalizer(),
                    new ValueFingerprinter(),
                ),
            ),
            new ContextDeliveryResolver(
                new ContextDistributor(new ContextPathResolver()),
                new ContextPathResolver()
            ),
            new RenderedTreeFactory(new RenderedElementFactory($this->typeRegistry()))
        );
    }

    /**
     * @return AbstractContentDataLoader<Struct>&MockObject
     */
    private function loaderReturning(ContentDataLoaderResult ...$results): AbstractContentDataLoader&MockObject
    {
        $loader = $this->loader();
        $loader->method('load')->willReturnOnConsecutiveCalls(...array_values($results));

        return $loader;
    }

    /**
     * @return AbstractContentDataLoader<Struct>&MockObject
     */
    private function loader(): AbstractContentDataLoader&MockObject
    {
        $loader = $this->createMock(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));

        return $loader;
    }

    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        $specs = [
            'CT:Section' => ContentSystemElementTypeSpecificationBuilder::create('CT:Section')->build(),
            'CT:Box' => ContentSystemElementTypeSpecificationBuilder::create('CT:Box')->build(),
            'CT:Blog' => ContentSystemElementTypeSpecificationBuilder::create('CT:Blog')
                ->reference('blog', StubStruct::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]
        );

        return $registry;
    }
}
