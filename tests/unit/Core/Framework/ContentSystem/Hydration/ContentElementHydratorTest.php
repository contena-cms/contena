<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Hydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\Hydration\ContentElementHydrator;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\DataContextResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Contena\Core\Test\Stub\ContentSystem\StubStruct;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentElementHydrator::class)]
class ContentElementHydratorTest extends TestCase
{
    private ChannelContext $context;

    private RenderingCacheContext $cacheContext;

    protected function setUp(): void
    {
        $this->context = Generator::generateChannelContext();
        $this->cacheContext = new RenderingCacheContext();
    }

    #[TestDox('loads data for elements with requirements and propagates cache tags')]
    public function testHydrateLoadsDataForElementsWithRequirements(): void
    {
        $element = ContentElementBuilder::create('blog-card')
            ->withDataRequirement('blog', 'entity', new StubLoaderConfig())
            ->build();

        $struct = new StubStruct();
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached($struct, 'blog-abc', 'blog-def'));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        $result = iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertCount(1, $result);
        static::assertSame($struct, $element->getProperty('blog'));
        static::assertSame(['blog-abc', 'blog-def'], $this->cacheContext->getTags());
    }

    #[TestDox('skips elements without data requirements')]
    public function testHydrateSkipsElementsWithoutRequirements(): void
    {
        $element = ContentElementBuilder::create('text-block')->build();

        $hydrator = $this->createHydrator();

        $result = iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertCount(1, $result);
    }

    #[TestDox('skips setting property when loader result has no data')]
    public function testHydrateSkipsPropertyWhenResultHasNoData(): void
    {
        $element = ContentElementBuilder::create('blog-card')
            ->withDataRequirement('blog', 'entity', new StubLoaderConfig())
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::notFound());

        $hydrator = $this->createHydrator(['entity' => $loader]);

        iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertNull($element->getProperty('blog'));
    }

    #[TestDox('resolves context after all data has been loaded')]
    public function testHydrateResolvesContextAfterAllDataLoaded(): void
    {
        $child = ContentElementBuilder::create('consumer')
            ->withConsumer('blog', ContextType::Single, required: false)
            ->build();

        $element = ContentElementBuilder::create('section')
            ->withProperty('blog', 'blog-data')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $hydrator = $this->createHydrator();

        iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertSame('blog-data', $child->getProperty('blog'));
    }

    #[TestDox('disables cache when loader result is not cache aware')]
    public function testHydrateDisablesCacheWhenResultIsNotCacheAware(): void
    {
        $element = ContentElementBuilder::create('dynamic')
            ->withDataRequirement('data', 'entity', new StubLoaderConfig())
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::uncacheable(new StubStruct()));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertTrue($this->cacheContext->isDisabled());
    }

    #[TestDox('recurses into slot children for hydration')]
    public function testHydrateRecursesIntoSlotChildren(): void
    {
        $child = ContentElementBuilder::create('child')
            ->withDataRequirement('item', 'entity', new StubLoaderConfig())
            ->build();

        $parent = ContentElementBuilder::create('parent')
            ->withSlot('content', [$child])
            ->build();

        $childStruct = new StubStruct();
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached($childStruct));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        iterator_to_array($hydrator->hydrate([$parent], $this->context, new Request(), $this->cacheContext), false);

        static::assertSame($childStruct, $child->getProperty('item'));
    }

    #[TestDox('yields nothing when element list is empty')]
    public function testHydrateYieldsNothingForEmptyElementList(): void
    {
        $hydrator = $this->createHydrator();

        $result = iterator_to_array($hydrator->hydrate([], $this->context, new Request(), $this->cacheContext), false);

        static::assertSame([], $result);
    }

    /**
     * @param array<string, AbstractContentDataLoader<Struct>> $loaders
     */
    private function createHydrator(array $loaders = []): ContentElementHydrator
    {
        $factories = [];
        foreach ($loaders as $key => $loader) {
            $factories[$key] = static fn () => $loader;
        }

        return new ContentElementHydrator(
            new DataLoaderProvider(new ServiceLocator($factories)),
            new DataContextResolver(new ContextPathResolver()),
        );
    }
}
