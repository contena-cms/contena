<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutCollection;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityLayoutContextFactory::class)]
class EntityLayoutContextFactoryTest extends TestCase
{
    private EntityLayoutResolver&Stub $layoutResolver;

    private EntityLayoutContextFactory $factory;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->layoutResolver = static::createStub(EntityLayoutResolver::class);
        $this->factory = new EntityLayoutContextFactory(
            $this->layoutResolver,
            static::createStub(RootContextMapper::class),
        );
        $this->ids = new IdsCollection();
    }

    #[DataProvider('supportsProvider')]
    #[TestDox('reports whether $_dataName is supported')]
    public function testSupports(string $path, bool $expected): void
    {
        $definition = $this->createDefinitionMock('/blog/');

        static::assertSame($expected, $this->factory->supports($path, $definition));
    }

    #[TestDox('resolves layout ID from resolver')]
    public function testResolveLayoutIdReturnsLayoutId(): void
    {
        $layoutId = $this->ids->get('layout');
        $entityId = $this->ids->get('entity');

        $definition = $this->createDefinitionMock('/blog/', 'blog', 'blogId', '{blogId}');

        $this->layoutResolver->method('findLayoutId')
            ->willReturn($layoutId);

        $repository = $this->createRepository();
        $context = Generator::generateChannelContext();

        $result = $this->factory->resolveLayoutId('/blog/' . $entityId, $context, $repository, $definition);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('resolves specification data without requiring a layout assignment')]
    public function testReturnsSpecificationDataFromDefinition(): void
    {
        $entityId = $this->ids->get('entity');
        $placeholders = PlaceholderValues::from(['blogId' => $entityId]);

        $this->layoutResolver->method('resolvePlaceholders')
            ->willReturn($placeholders);

        $definition = $this->createDefinitionMock('/blog/', 'blog', 'blogId', '{blogId}');
        $definition->method('getPageDataRequirements')->willReturn([]);

        $context = Generator::generateChannelContext();

        $result = $this->factory->resolveSpecificationData(
            '/blog/' . $entityId,
            new Request(),
            $context,
            $definition
        );

        static::assertSame($placeholders, $result->placeholderValues);
        static::assertSame([], $result->dataRequirements);
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('resolveTargetElementIdProvider')]
    #[TestDox('returns the target element id for $_dataName')]
    public function testResolveTargetElementId(array $query, ?string $expected): void
    {
        static::assertSame($expected, $this->factory->resolveTargetElementId(new Request($query)));
    }

    #[TestDox('returns cache tags derived from entity ID in path')]
    public function testResolveCacheTagsReturnsDerivedTagsFromPath(): void
    {
        $entityId = $this->ids->get('entity');

        $definition = $this->createDefinitionMock('/blog/', 'blog', 'blogId', '{blogId}');
        $definition->method('getCacheTags')->willReturn(['blog-' . $entityId]);

        $result = $this->factory->resolveCacheTags('/blog/' . $entityId, $definition);

        static::assertSame(['blog-' . $entityId], $result);
    }

    #[TestDox('throws when no layout assignment found')]
    public function testResolveLayoutIdThrowsWhenNoAssignment(): void
    {
        $entityId = $this->ids->get('entity');

        $definition = $this->createDefinitionMock('/blog/', 'blog', 'blogId', '{blogId}');

        $this->layoutResolver->method('findLayoutId')
            ->willReturn(null);

        $repository = $this->createRepository();
        $context = Generator::generateChannelContext();

        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'blog',
            $entityId,
            $context->getChannel()->getId()
        ));

        $this->factory->resolveLayoutId('/blog/' . $entityId, $context, $repository, $definition);
    }

    #[TestDox('throws when path does not match expected route pattern')]
    public function testResolveCacheTagsThrowsWhenPathDoesNotMatchRoutePattern(): void
    {
        $definition = $this->createDefinitionMock('/blog/', 'blog', 'blogId', '{blogId}');
        $definition->method('getCacheTags')->willReturn([]);

        $this->expectExceptionObject(ContentSystemException::invalidEntityPath(
            'blog',
            '/completely/invalid',
            '/blog/{blogId}'
        ));

        $this->factory->resolveCacheTags('/completely/invalid', $definition);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function supportsProvider(): iterable
    {
        yield 'a path matching the definition prefix' => ['/blog/abc123', true];
        yield 'a path not matching the definition prefix' => ['/category/abc123', false];
    }

    /**
     * @return iterable<string, array{array<string, string>, ?string}>
     */
    public static function resolveTargetElementIdProvider(): iterable
    {
        yield 'an element id present in the request query' => [['elementId' => 'elem-42'], 'elem-42'];
        yield 'no element id in the request query' => [[], null];
    }

    /**
     * @return StaticEntityRepository<BlogContentLayoutCollection>
     */
    private function createRepository(): StaticEntityRepository
    {
        /** @var StaticEntityRepository<BlogContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([]);

        return $repository;
    }

    private function createDefinitionMock(
        string $pathPrefix = '/blog/',
        string $entityType = 'blog',
        string $entityIdField = 'blogId',
        string $routePattern = '{blogId}'
    ): AbstractContentLayoutAssignableDefinition&Stub {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutPathPrefix')->willReturn($pathPrefix);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);
        $definition->method('getContentLayoutEntityIdField')->willReturn($entityIdField);
        $definition->method('getContentLayoutRoutePattern')->willReturn($routePattern);

        return $definition;
    }
}
