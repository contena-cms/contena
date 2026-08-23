<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Contena\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Contena\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Contena\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Contena\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Contena\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;

/**
 * @internal
 */
#[CoversClass(AvailableContextResolver::class)]
class AvailableContextResolverTest extends TestCase
{
    #[TestDox('returns the bound source root-ambient context for a top-level element, or empty when the source exposes none (header/footer)')]
    public function testTopLevelReceivesRootAmbient(): void
    {
        $root = new ContentElement('root-1', 'CT:Block');

        $rootContext = $this->rootAmbientBlogContext();

        static::assertSame($rootContext, $this->resolver()->resolve('root-1', [$root], $rootContext));
        static::assertSame([], $this->resolver()->resolve('root-1', [$root], []));
    }

    #[TestDox('resolves ancestor provider context with the FQCN from the provider type spec for a nested element')]
    public function testNestedReceivesAncestorProvider(): void
    {
        $child = new ContentElement('child-1', 'CT:Block');
        $root = new ContentElement(
            'root-1',
            'CT:Provider',
            [],
            [],
            ['content' => new SlotContent([$child])],
            new ContextDefinitions(
                ['blog' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                [],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], []);

        static::assertCount(1, $available);
        static::assertSame('blog', $available[0]->contextKey);
        static::assertSame(ChannelBlogEntity::class, $available[0]->fqcn);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertSame(DistributionStrategy::Broadcast, $available[0]->distribution);
    }

    #[TestDox('excludes a top-level sibling root-ambient context from a nested element')]
    public function testNestedDoesNotReceiveRootAmbient(): void
    {
        $child = new ContentElement('child-1', 'CT:Block');
        $root = new ContentElement('root-1', 'CT:Block', [], [], ['content' => new SlotContent([$child])]);

        $rootContext = $this->rootAmbientBlogContext();

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('exposes a backed ancestor provider to its direct child but not past a non-redistributing intermediate')]
    public function testProviderContextStopsAtNonRedistributingIntermediate(): void
    {
        $grandchild = new ContentElement('grandchild-1', 'CT:Block');
        $child = new ContentElement('child-1', 'CT:Block', [], [], ['content' => new SlotContent([$grandchild])]);
        $root = new ContentElement(
            'root-1',
            'CT:Provider',
            [],
            [],
            ['content' => new SlotContent([$child])],
            new ContextDefinitions(
                ['blog' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                [],
            ),
        );

        $resolver = $this->resolver();

        static::assertSame(['blog'], $this->keys($resolver->resolve('child-1', [$root], [])));
        static::assertSame([], $resolver->resolve('grandchild-1', [$root], []));
    }

    #[TestDox('re-exposes incoming root-ambient context through a redistributing intermediate with the inflowing type')]
    public function testRedistributeReExposesIncomingRootAmbient(): void
    {
        $child = new ContentElement('child-1', 'CT:Block');
        $root = new ContentElement(
            'root-1',
            'CT:Block',
            [],
            [],
            ['content' => new SlotContent([$child])],
            new ContextDefinitions(
                [],
                ['blog' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], $this->rootAmbientBlogContext());

        static::assertCount(1, $available);
        static::assertSame('blog', $available[0]->contextKey);
        static::assertSame(ChannelBlogEntity::class, $available[0]->fqcn);
        static::assertSame(ContextType::Single, $available[0]->contextType);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertSame(DistributionStrategy::Broadcast, $available[0]->distribution);
    }

    #[TestDox('remaps the re-exposed key to the consumer alias while keeping the inflowing type')]
    public function testRedistributeConsumerAliasRemapsExposedKey(): void
    {
        $child = new ContentElement('child-1', 'CT:Block');
        $root = new ContentElement(
            'root-1',
            'CT:Block',
            [],
            [],
            ['content' => new SlotContent([$child])],
            new ContextDefinitions(
                [],
                ['blog' => new ContextConsumer(ContextType::Single, required: false, redistribute: true, consumerAlias: 'item')],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], $this->rootAmbientBlogContext());

        static::assertCount(1, $available);
        static::assertSame('item', $available[0]->contextKey);
        static::assertSame(ChannelBlogEntity::class, $available[0]->fqcn);
    }

    #[TestDox('does not re-expose a redistribute consumer whose key is absent from the incoming context')]
    public function testRedistributeWithoutMatchingIncomingKeyExposesNothing(): void
    {
        // F1 regression guard: a redistribute consumer re-exposes only a key that actually flows into the
        // element, so unconditional re-exposure cannot re-open the over-permissive availability leak.
        $child = new ContentElement('child-1', 'CT:Block');
        $root = new ContentElement(
            'root-1',
            'CT:Block',
            [],
            [],
            ['content' => new SlotContent([$child])],
            new ContextDefinitions(
                [],
                ['category' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)],
            ),
        );

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $this->rootAmbientBlogContext()));
    }

    #[TestDox('does not re-expose incoming context through a consumer that does not redistribute')]
    public function testNonRedistributingConsumerDoesNotReExposeIncomingContext(): void
    {
        $child = new ContentElement('child-1', 'CT:Block');
        $root = new ContentElement(
            'root-1',
            'CT:Block',
            [],
            [],
            ['content' => new SlotContent([$child])],
            new ContextDefinitions(
                [],
                ['blog' => new ContextConsumer(ContextType::Single, required: false, redistribute: false)],
            ),
        );

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $this->rootAmbientBlogContext()));
    }

    #[TestDox('accumulates redistributed context across multiple intermediates down to a deep descendant')]
    public function testRedistributeChainsAcrossMultipleLevels(): void
    {
        $deep = new ContentElement('deep-1', 'CT:Block');
        $level2 = new ContentElement(
            'level-2',
            'CT:Block',
            [],
            [],
            ['content' => new SlotContent([$deep])],
            new ContextDefinitions([], ['blog' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)]),
        );
        $root = new ContentElement(
            'root-1',
            'CT:Block',
            [],
            [],
            ['content' => new SlotContent([$level2])],
            new ContextDefinitions([], ['blog' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)]),
        );

        $available = $this->resolver()->resolve('deep-1', [$root], $this->rootAmbientBlogContext());

        static::assertCount(1, $available);
        static::assertSame('blog', $available[0]->contextKey);
        static::assertSame('level-2', $available[0]->providerElementId);
    }

    #[TestDox('returns an empty set for an unknown element id')]
    public function testUnknownElementYieldsEmpty(): void
    {
        $root = new ContentElement('root-1', 'CT:Block');

        static::assertSame([], $this->resolver()->resolve('missing', [$root], []));
    }

    /**
     * @param list<ProvidedContext> $available
     *
     * @return list<string>
     */
    private function keys(array $available): array
    {
        return array_map(static fn (ProvidedContext $provided): string => $provided->contextKey, $available);
    }

    /**
     * @return list<ProvidedContext>
     */
    private function rootAmbientBlogContext(): array
    {
        return [new ProvidedContext(
            contextKey: 'blog',
            fqcn: ChannelBlogEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];
    }

    private function resolver(): AvailableContextResolver
    {
        $providerSpec = new ContentSystemElementTypeSpecification(
            'CT:Provider',
            'Provider',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            ['blog' => new PropertySpecification(
                'blog',
                new PropertyType(ChannelBlogEntity::class, false, null, null),
                false,
                '',
                '',
                null,
            )],
            [],
        );

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'CT:Provider');
        $registry->method('get')->willReturn($providerSpec);

        // A single complete loader backs the provider's own `blog` property, so its declared provider
        // resolves on its element (Level 2) and is exposed to descendants.
        $typeResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $typeResolver->method('resolve')->willReturn(new ContentSystemDataLoaderMap(
            ['blog_loader' => [new LoaderTypeCapability(ChannelBlogEntity::class)]],
            ['blog_loader' => new LoaderConfigSpecification([])],
        ));

        $configSerializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configSerializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        $elementResolver = new ElementResolver($registry, $typeResolver, $configSerializers, static::createStub(DataLoaderProvider::class));

        return new AvailableContextResolver($registry, $elementResolver);
    }
}
