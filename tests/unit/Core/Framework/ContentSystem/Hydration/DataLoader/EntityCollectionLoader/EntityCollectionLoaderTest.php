<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Exception\ChannelRepositoryNotFoundException;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityCollectionLoader::class)]
class EntityCollectionLoaderTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('declares the channel collection class and entity generic for an entity with a channel definition')]
    public function testProducibleTypesDeclaresChannelCollectionForEntityWithVariant(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createChannelDefinitionRegistry(new ChannelBlogDefinition()),
            $this->createDefinitionRegistry(new BlogDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(ChannelBlogCollection::class, $capabilities[0]->producedType);
        static::assertSame([ChannelBlogEntity::class], $capabilities[0]->genericParameters);
        static::assertSame(['entity' => 'blog'], $capabilities[0]->configTemplate);
    }

    #[TestDox('declares the base collection class for an entity without a channel definition')]
    public function testProducibleTypesDeclaresBaseCollectionForEntityWithoutVariant(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(new MediaDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaCollection::class, $capabilities[0]->producedType);
        static::assertSame([MediaEntity::class], $capabilities[0]->genericParameters);
    }

    #[TestDox('skips bare EntityCollection definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsBareEntityCollectionAndContinues(): void
    {
        // Bare-collection definition first, valid definition second: a continue→break regression would
        // drop the valid type and leave an empty result.
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'bare' => $this->definitionStub(EntityCollection::class, MediaEntity::class, 'bare'),
            'media' => $this->definitionStub(MediaCollection::class, MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getChannelDefinitions')->willReturn([]);

        $loader = new EntityCollectionLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaCollection::class, $capabilities[0]->producedType);
    }

    #[TestDox('skips mapping definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsMappingDefinitionsAndContinues(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'blog_category' => static::createStub(MappingEntityDefinition::class),
            'media' => $this->definitionStub(MediaCollection::class, MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getChannelDefinitions')->willReturn([]);

        $loader = new EntityCollectionLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaCollection::class, $capabilities[0]->producedType);
    }

    #[TestDox('resolves the channel collection class for a config naming an entity with a variant')]
    public function testResolveProducedTypeReturnsChannelCollection(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createChannelDefinitionRegistry(new ChannelBlogDefinition()),
            $this->createDefinitionRegistry(new BlogDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        static::assertSame(
            ChannelBlogCollection::class,
            $loader->resolveProducedType(new EntityLoaderConfig('blog', 'blogIds', [])),
        );
    }

    #[TestDox('throws when resolving a config that names an unknown entity')]
    public function testResolveProducedTypeThrowsForUnknownEntity(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(),
            static::createStub(EntityCacheTagResolver::class),
        );

        $this->expectExceptionObject(ContentSystemException::unknownLoaderEntity('ghost'));

        $loader->resolveProducedType(new EntityLoaderConfig('ghost', 'ghostIds', []));
    }

    #[TestDox('throws when resolving produced type for a config that is not an EntityLoaderConfig')]
    public function testResolveProducedTypeThrowsForWrongConfigType(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, StubLoaderConfig::class),
        );

        $this->createMinimalLoader()->resolveProducedType(new StubLoaderConfig());
    }

    #[TestDox('returns the declared channel collection class when no IDs are provided')]
    public function testEmptyCollectionPathReturnsDeclaredProducedType(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createChannelDefinitionRegistry(new ChannelBlogDefinition()),
            $this->createDefinitionRegistry(new BlogDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $result = $loader->load(
            ContentElementBuilder::create('blog-grid')->build(),
            new DataRequirement('blogs', 'entity_collection', new EntityLoaderConfig('blog', 'blogIds', [])),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(ChannelBlogCollection::class, $result->data);
        static::assertSame($loader->producibleTypes()[0]->producedType, $result->data::class);
    }

    #[TestDox('returns cached collection with resolved tags for all loaded entities')]
    public function testLoadReturnsCachedCollectionWithResolvedTagsForAllEntities(): void
    {
        $id1 = 'blog-one';
        $id2 = 'blog-two';

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')
            ->willReturnCallback(static fn (EntityDefinition $def, string $id) => 'tag-' . $id);

        $loader = $this->createLoaderWithChannelRepo(
            'blog',
            new EntityCollection([$this->createEntityWithId($id1), $this->createEntityWithId($id2)]),
            $cacheTagResolver,
        );

        $result = $loader->load(
            ContentElementBuilder::create('blog-grid')->withProperty('blogIds', [$id1, $id2])->build(),
            new DataRequirement('blogs', 'entity_collection', new EntityLoaderConfig('blog', 'blogIds', [])),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertTrue($result->isCacheAware());
        static::assertInstanceOf(EntityCollection::class, $result->data);
        static::assertContains('tag-' . $id1, $result->getCacheTags());
        static::assertContains('tag-' . $id2, $result->getCacheTags());
        static::assertCount(2, $result->getCacheTags());
    }

    #[TestDox('falls back to plain repository when channel repository is not found')]
    public function testLoadFallsBackToPlainRepositoryWhenChannelRepoNotFound(): void
    {
        $categoryId = 'category-id';
        $entity = $this->createEntityWithId($categoryId);
        $collection = new EntityCollection([$entity]);

        $plainRepo = new StaticEntityRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('category');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('category-route-' . $categoryId);

        $scDefRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getChannelRepository')
            ->willThrowException(new ChannelRepositoryNotFoundException('category'));

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getRepository')->willReturn($plainRepo);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load(
            ContentElementBuilder::create('category-grid')->withProperty('categoryIds', [$categoryId])->build(),
            new DataRequirement('categories', 'entity_collection', new EntityLoaderConfig('category', 'categoryIds', [])),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertTrue($result->isCacheAware());
        static::assertSame(['category-route-' . $categoryId], $result->getCacheTags());
        static::assertInstanceOf(EntityCollection::class, $result->data);
    }

    #[TestDox('lowercases entity IDs before loading')]
    public function testLoadLowercasesEntityIds(): void
    {
        $blogId = $this->ids->get('blog');
        $upperCaseId = strtoupper($blogId);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('blog', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $loader->load(
            ContentElementBuilder::create('blog-grid')->withProperty('blogIds', [$upperCaseId])->build(),
            new DataRequirement('blogs', 'entity_collection', new EntityLoaderConfig('blog', 'blogIds', [])),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$blogId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entities')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $blogId = $this->ids->get('blog');

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('blog', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $loader->load(
            ContentElementBuilder::create('blog-grid')->withProperty('blogIds', [$blogId])->build(),
            new DataRequirement('blogs', 'entity_collection', new EntityLoaderConfig('blog', 'blogIds', ['tags', 'cover'])),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('returns cached empty collection when property is null on element')]
    public function testLoadReturnsCachedEmptyWhenPropertyIsNull(): void
    {
        $config = new EntityLoaderConfig('blog', 'blogIds', []);
        $requirement = new DataRequirement('blogs', 'entity_collection', $config);
        // element has no 'blogIds' property → getProperty returns null
        $element = ContentElementBuilder::create('blog-grid')->build();
        $context = Generator::generateChannelContext();

        $loader = $this->createLoaderWithDefinition('blog', EntityCollection::class);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(EntityCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns cached empty collection when entity IDs contain no valid strings')]
    public function testLoadReturnsCachedEmptyWhenEntityIdsContainNoStrings(): void
    {
        $config = new EntityLoaderConfig('blog', 'blogIds', []);
        $requirement = new DataRequirement('blogs', 'entity_collection', $config);
        // non-string values get filtered out
        $element = ContentElementBuilder::create('blog-grid')
            ->withProperty('blogIds', [123, null, true])
            ->build();
        $context = Generator::generateChannelContext();

        $loader = $this->createLoaderWithDefinition('blog', EntityCollection::class);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(EntityCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null for an entity')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $blogId = 'uncacheable-blog';

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $loader = $this->createLoaderWithChannelRepo(
            'blog',
            new EntityCollection([$this->createEntityWithId($blogId)]),
            $cacheTagResolver,
        );

        $result = $loader->load(
            ContentElementBuilder::create('blog-grid')->withProperty('blogIds', [$blogId])->build(),
            new DataRequirement('blogs', 'entity_collection', new EntityLoaderConfig('blog', 'blogIds', [])),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertFalse($result->isCacheAware());
        static::assertInstanceOf(EntityCollection::class, $result->data);
    }

    #[DataProvider('returnsNullDataProvider')]
    #[TestDox('returns null data when $_dataName')]
    public function testLoadReturnsNullData(DataRequirement $requirement, ContentElement $element): void
    {
        $loader = $this->createMinimalLoader();
        $context = Generator::generateChannelContext();

        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound instead of throwing when the configured entity is not registered')]
    public function testLoadReturnsNotFoundForUnregisteredEntity(): void
    {
        // has() defaults to false on the stub registry, so the loader must short-circuit to notFound
        // rather than letting getByEntityName()/getRepository() throw (loaders must never throw).
        $loader = new EntityCollectionLoader(
            static::createStub(ChannelDefinitionInstanceRegistry::class),
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(EntityCacheTagResolver::class),
        );

        $result = $loader->load(
            ContentElementBuilder::create('blog-grid')->withProperty('ghostIds', ['id-1'])->build(),
            new DataRequirement('ghosts', 'entity_collection', new EntityLoaderConfig('ghost', 'ghostIds', [])),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @return iterable<string, array{DataRequirement, ContentElement}>
     */
    public static function returnsNullDataProvider(): iterable
    {
        yield 'config is not an EntityLoaderConfig' => [
            new DataRequirement('blogs', 'entity_collection', new StubLoaderConfig()),
            ContentElementBuilder::create('blog-grid')->build(),
        ];

        yield 'property value is not an array' => [
            new DataRequirement('blogs', 'entity_collection', new EntityLoaderConfig('blog', 'blogIds', [])),
            ContentElementBuilder::create('blog-grid')->withProperty('blogIds', 'not-an-array')->build(),
        ];
    }

    /**
     * @param class-string<EntityCollection<Entity>> $collectionClass
     */
    private function createLoaderWithDefinition(string $entityName, string $collectionClass): EntityCollectionLoader
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getCollectionClass')->willReturn($collectionClass);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityCollectionLoader(
            static::createStub(ChannelDefinitionInstanceRegistry::class),
            $defRegistry,
            static::createStub(EntityCacheTagResolver::class),
        );
    }

    /**
     * @param EntityCollection<Entity> $collection
     */
    private function createLoaderWithChannelRepo(
        string $entityName,
        EntityCollection $collection,
        EntityCacheTagResolver $cacheTagResolver,
    ): EntityCollectionLoader {
        $scRepo = new StaticChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $scDefRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createLoaderWithCallableRepo(string $entityName, callable $callback): EntityCollectionLoader
    {
        $scRepo = new StaticChannelRepository([$callback]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $scDefRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createMinimalLoader(): EntityCollectionLoader
    {
        $scDefRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createDefinitionRegistry(EntityDefinition ...$definitions): DefinitionInstanceRegistry
    {
        $registry = new DefinitionInstanceRegistry(new Container(), [], []);
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        return $registry;
    }

    private function createChannelDefinitionRegistry(EntityDefinition ...$definitions): ChannelDefinitionInstanceRegistry
    {
        $registry = new ChannelDefinitionInstanceRegistry('channel_definition.', new Container(), [], []);
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        return $registry;
    }

    /**
     * @param class-string $collectionClass
     * @param class-string<Entity> $entityClass
     */
    private function definitionStub(string $collectionClass, string $entityClass, string $entityName): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getCollectionClass')->willReturn($collectionClass);
        $definition->method('getEntityClass')->willReturn($entityClass);
        $definition->method('getEntityName')->willReturn($entityName);

        return $definition;
    }

    private function createEntityWithId(string $id): Entity
    {
        $entity = new Entity();
        $entity->setUniqueIdentifier($id);

        return $entity;
    }
}
