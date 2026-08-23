<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Contena\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfig;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationDataLoader::class)]
class NavigationDataLoaderTest extends TestCase
{
    private NavigationLoaderInterface&Stub $navigationLoader;

    private NavigationAliasResolver $aliasResolver;

    private NavigationDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $this->aliasResolver = new NavigationAliasResolver();
        $this->dataLoader = new NavigationDataLoader($this->navigationLoader, $this->aliasResolver);
    }

    #[TestDox('returns navigation source type identifier')]
    public function testGetRequirementTypeReturnsNavigationString(): void
    {
        static::assertSame('navigation', NavigationDataLoader::getRequirementType());
    }

    #[TestDox('declares Tree as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(Tree::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('loads navigation tree with explicit rootId from config')]
    public function testLoadWithExplicitRootIdCallsNavigationLoader(): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => $activeId]);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('resolves main-navigation alias to channel navigation category ID')]
    public function testLoadResolvesMainNavigationAliasToNavigationCategoryId(): void
    {
        $navCategoryId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => $activeId]);
        $config = new NavigationLoaderConfig(rootId: 'main-navigation', depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();
        $context->getChannel()->setNavigationCategoryId($navCategoryId);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $navCategoryId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    /**
     * @param non-empty-string $alias
     * @param callable(ChannelEntity, string): void $assignRootToChannel
     */
    #[TestDox('resolves the optional service and footer roots to their channel category')]
    #[DataProvider('optionalRootProvider')]
    public function testLoadResolvesTheOptionalChannelRoots(string $alias, callable $assignRootToChannel): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => $activeId]);
        $config = new NavigationLoaderConfig(rootId: $alias, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();
        $assignRootToChannel($context->getChannel(), $rootId);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    /**
     * @return iterable<string, array{non-empty-string, callable(ChannelEntity, string): void}>
     */
    public static function optionalRootProvider(): iterable
    {
        yield 'service navigation' => ['service-navigation', static function (ChannelEntity $channel, string $rootId): void {
            $channel->setServiceCategoryId($rootId);
        }];

        yield 'footer navigation' => ['footer-navigation', static function (ChannelEntity $channel, string $rootId): void {
            $channel->setFooterCategoryId($rootId);
        }];
    }

    #[TestDox('reads active ID from custom activeProperty name')]
    public function testLoadReadsActiveIdFromCustomActiveProperty(): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['categoryId' => $activeId]);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'categoryId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('returns cachedExternally result with empty cache tags')]
    public function testLoadReturnsCachedExternallyResult(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new NavigationLoaderConfig(rootId: $rootId);
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();

        $this->navigationLoader->method('load')->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('passes configured depth to navigation loader')]
    public function testLoadPassesConfiguredDepthToNavigationLoader(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 5);
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 5)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $dataLoader->load($element, $requirement, $context, new Request());
    }

    #[TestDox('defaults to main-navigation alias when rootId is null in config')]
    public function testLoadDefaultsToMainNavigationWhenRootIdIsNull(): void
    {
        $navCategoryId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => $activeId]);
        // rootId is null — defaults to 'main-navigation'
        $config = new NavigationLoaderConfig(rootId: null, depth: 3, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();
        $context->getChannel()->setNavigationCategoryId($navCategoryId);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $navCategoryId, 3)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('uses rootId as activeId when element active property is missing')]
    public function testLoadUsesRootIdAsActiveIdWhenActivePropertyIsMissing(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();

        $elementMissing = new ContentElement(id: Uuid::randomHex(), component: 'test');

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $resultMissing = $dataLoader->load($elementMissing, $requirement, $context, new Request());

        static::assertTrue($resultMissing->hasData());
        static::assertSame($tree, $resultMissing->data);
    }

    #[TestDox('uses rootId as activeId when element active property is an empty string')]
    public function testLoadUsesRootIdAsActiveIdWhenActivePropertyIsEmptyString(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();

        $elementEmpty = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => '']);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $resultEmpty = $dataLoader->load($elementEmpty, $requirement, $context, new Request());

        static::assertTrue($resultEmpty->hasData());
        static::assertSame($tree, $resultEmpty->data);
    }

    #[TestDox('falls back to the channel navigation depth when the config declares none')]
    public function testLoadFallsBackToChannelDepthWhenConfigDeclaresNone(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new NavigationLoaderConfig(rootId: $rootId);
        $requirement = new DataRequirement('navKey', 'navigation', $config);

        $context = Generator::generateChannelContext();
        $context->getChannel()->setNavigationCategoryDepth(4);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 4)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertSame($tree, $result->data);
    }

    #[TestDox('an explicitly configured depth still wins over the channel setting')]
    public function testLoadPrefersConfiguredDepthOverChannelDepth(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 1);
        $requirement = new DataRequirement('navKey', 'navigation', $config);

        $context = Generator::generateChannelContext();
        $context->getChannel()->setNavigationCategoryDepth(4);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 1)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertSame($tree, $result->data);
    }

    #[TestDox('falls back to the root when the activeId placeholder was not resolved')]
    public function testLoadFallsBackToRootWhenActiveIdIsAnUnresolvedPlaceholder(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        // A layout that is not rooted on a category leaves "{{categoryId}}" in place.
        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => '{{categoryId}}']);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertSame($tree, $result->data);
    }

    #[TestDox('returns notFound result when an alias does not resolve because the channel has no such category')]
    public function testLoadReturnsNotFoundWhenAliasDoesNotResolve(): void
    {
        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new NavigationLoaderConfig(rootId: 'service-navigation', depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);

        $context = Generator::generateChannelContext();
        static::assertNull($context->getChannel()->getServiceCategoryId());

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        // All three together pin notFound() rather than uncacheable(), which differs only in its tags.
        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when config is not a NavigationLoaderConfig instance')]
    public function testLoadReturnNotFoundWhenConfigIsNotNavigationLoaderConfig(): void
    {
        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('navKey', 'navigation', $wrongConfig);
        $context = Generator::generateChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load($element, $requirement, $context, new Request());

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
