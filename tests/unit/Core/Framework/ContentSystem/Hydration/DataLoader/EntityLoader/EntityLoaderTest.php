<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\ArrayEntity;
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
#[CoversClass(EntityLoader::class)]
class EntityLoaderTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    /**
     * @param list<EntityDefinition> $channelDefinitions
     * @param class-string $expectedProducedType
     * @param array<string, mixed> $expectedConfigTemplate
     */
    #[DataProvider('declaresProducibleTypeProvider')]
    #[TestDox('declares the producible type for $_dataName')]
    public function testProducibleTypesDeclaresExpectedProducedType(
        array $channelDefinitions,
        EntityDefinition $definition,
        string $expectedProducedType,
        array $expectedConfigTemplate,
    ): void {
        $loader = new EntityLoader(
            $this->createChannelDefinitionRegistry(...$channelDefinitions),
            $this->createDefinitionRegistry($definition),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame($expectedProducedType, $capabilities[0]->producedType);
        static::assertSame($expectedConfigTemplate, $capabilities[0]->configTemplate);
    }

    #[TestDox('skips ArrayEntity definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsArrayEntityAndContinues(): void
    {
        // ArrayEntity definition first, valid definition second: a continue→break regression would
        // drop the valid type and leave an empty result.
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'array' => $this->definitionStub(ArrayEntity::class, 'array'),
            'media' => $this->definitionStub(MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getChannelDefinitions')->willReturn([]);

        $loader = new EntityLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaEntity::class, $capabilities[0]->producedType);
    }

    #[TestDox('skips mapping definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsMappingDefinitionsAndContinues(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'blog_category' => static::createStub(MappingEntityDefinition::class),
            'media' => $this->definitionStub(MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getChannelDefinitions')->willReturn([]);

        $loader = new EntityLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaEntity::class, $capabilities[0]->producedType);
    }

    #[TestDox('resolves the channel entity class for a config naming an entity with a variant')]
    public function testResolveProducedTypeReturnsChannelClass(): void
    {
        $loader = $this->blogEntityLoader();

        static::assertSame(
            ChannelBlogEntity::class,
            $loader->resolveProducedType(new EntityLoaderConfig('blog', 'blogId', [])),
        );
    }

    #[TestDox('resolves the base entity class for a config naming an entity without a variant')]
    public function testResolveProducedTypeReturnsBaseClass(): void
    {
        $loader = new EntityLoader(
            $this->createChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(new MediaDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        static::assertSame(
            MediaEntity::class,
            $loader->resolveProducedType(new EntityLoaderConfig('media', 'mediaId', [])),
        );
    }

    #[TestDox('throws when resolving a config that names an unknown entity')]
    public function testResolveProducedTypeThrowsForUnknownEntity(): void
    {
        $loader = new EntityLoader(
            $this->createChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(),
            static::createStub(EntityCacheTagResolver::class),
        );

        $this->expectExceptionObject(ContentSystemException::unknownLoaderEntity('ghost'));

        $loader->resolveProducedType(new EntityLoaderConfig('ghost', 'ghostId', []));
    }

    #[TestDox('throws when resolving produced type for a config that is not an EntityLoaderConfig')]
    public function testResolveProducedTypeThrowsForWrongConfigType(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, StubLoaderConfig::class),
        );

        $this->createMinimalLoader()->resolveProducedType(new StubLoaderConfig());
    }

    #[TestDox('declares exactly the required config keys the serializer needs to decode a config (drift guard)')]
    public function testConfigSpecificationRequiredKeysMatchSerializerRequiredKeys(): void
    {
        $loader = $this->blogEntityLoader();

        $requiredKeys = $loader->configSpecification()->requiredKeys();
        sort($requiredKeys);

        static::assertSame(['entity', 'property'], $requiredKeys);

        // Drive decode() purely from the keys the specification declares required: if the specification drops a
        // key the serializer requires (or decode() gains a new required key), decode() throws and this fails.
        // EntityLoaderConfigSerializerTest pins necessity (decode rejects either key's absence).
        $input = [];
        foreach ($requiredKeys as $key) {
            $input[$key] = 'blog';
        }

        new EntityLoaderConfigSerializer()->decode($input);
    }

    #[TestDox('returns cached result with cache tag when entity is loaded via channel repository')]
    public function testLoadReturnsCachedResultViaChannelRepository(): void
    {
        $blogId = $this->ids->get('blog');
        $entity = $this->createEntityWithId($blogId);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('blog-' . $blogId);

        $loader = $this->createLoaderWithChannelRepo('blog', new EntityCollection([$entity]), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'blog', 'blogId', $blogId);

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['blog-' . $blogId], $result->getCacheTags());
    }

    #[TestDox('falls back to context repository when channel repository is unavailable')]
    public function testLoadFallsBackToContextRepositoryWhenChannelRepoUnavailable(): void
    {
        $categoryId = $this->ids->get('category');
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

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $this->loadEntity($loader, 'category', 'categoryId', $categoryId);

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['category-route-' . $categoryId], $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $blogId = $this->ids->get('blog');
        $entity = $this->createEntityWithId($blogId);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $loader = $this->createLoaderWithChannelRepo('blog', new EntityCollection([$entity]), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'blog', 'blogId', $blogId);

        static::assertSame($entity, $result->data);
        static::assertFalse($result->isCacheAware());
    }

    #[TestDox('lowercases entity ID before passing it to the repository')]
    public function testLoadLowercasesEntityId(): void
    {
        $blogId = $this->ids->get('blog');
        $upperCaseId = strtoupper($blogId);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('blog', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $this->loadEntity($loader, 'blog', 'blogId', $upperCaseId);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$blogId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entity')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $blogId = $this->ids->get('blog');

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('blog', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $config = new EntityLoaderConfig('blog', 'blogId', ['tags', 'cover']);
        $requirement = new DataRequirement('blog', 'entity', $config);
        $element = ContentElementBuilder::create('blog-detail')
            ->withProperty('blogId', $blogId)
            ->build();

        $loader->load($element, $requirement, Generator::generateChannelContext(), new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('uses property name from config to look up element property')]
    public function testLoadUsesPropertyNameFromConfigToLookUpElementProperty(): void
    {
        $blogId = $this->ids->get('blog');
        $entity = $this->createEntityWithId($blogId);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('blog-' . $blogId);

        $loader = $this->createLoaderWithChannelRepo('blog', new EntityCollection([$entity]), $cacheTagResolver);

        $config = new EntityLoaderConfig('blog', 'customPropName', []);
        $requirement = new DataRequirement('blog', 'entity', $config);
        $element = ContentElementBuilder::create('blog-detail')
            ->withProperty('customPropName', $blogId)
            ->build();

        $result = $loader->load($element, $requirement, Generator::generateChannelContext(), new Request());

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
    }

    #[TestDox('returns notFound result when config is not EntityLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $requirement = new DataRequirement('blog', 'entity', new StubLoaderConfig());
        $element = ContentElementBuilder::create('blog-detail')->build();

        $result = $this->createMinimalLoader()->load($element, $requirement, Generator::generateChannelContext(), new Request());

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound result when element property is not a string')]
    public function testLoadReturnsNotFoundWhenPropertyIsNotString(): void
    {
        $config = new EntityLoaderConfig('blog', 'blogId', []);
        $requirement = new DataRequirement('blogId', 'entity', $config);
        $element = ContentElementBuilder::create('blog-detail')
            ->withProperty('blogId', 42)
            ->build();

        $result = $this->createMinimalLoader()->load($element, $requirement, Generator::generateChannelContext(), new Request());

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound result when entity is not found in repository')]
    public function testLoadReturnsNotFoundWhenEntityNotFoundInRepository(): void
    {
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $loader = $this->createLoaderWithChannelRepo('blog', new EntityCollection(), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'blog', 'blogId', 'blog-id');

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound instead of throwing when the configured entity is not registered')]
    public function testLoadReturnsNotFoundForUnregisteredEntity(): void
    {
        // has() defaults to false on the stub registry, so the loader must short-circuit to notFound
        // rather than letting getByEntityName()/getRepository() throw (loaders must never throw).
        $scDefRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $loader = new EntityLoader($scDefRegistry, $defRegistry, static::createStub(EntityCacheTagResolver::class));

        $result = $this->loadEntity($loader, 'ghost', 'ghostId', 'some-id');

        $this->assertNotFoundResult($result);
    }

    /**
     * @return iterable<string, array{list<EntityDefinition>, EntityDefinition, class-string, array<string, mixed>}>
     */
    public static function declaresProducibleTypeProvider(): iterable
    {
        yield 'an entity with a channel variant' => [[new ChannelBlogDefinition()], new BlogDefinition(), ChannelBlogEntity::class, ['entity' => 'blog']];
        yield 'an entity without a channel variant' => [[], new MediaDefinition(), MediaEntity::class, ['entity' => 'media']];
    }

    private function assertNotFoundResult(ContentDataLoaderResult $result): void
    {
        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @param non-empty-string $entityName
     * @param non-empty-string $propertyName
     */
    private function loadEntity(
        EntityLoader $loader,
        string $entityName,
        string $propertyName,
        string $propertyValue,
    ): ContentDataLoaderResult {
        $config = new EntityLoaderConfig($entityName, $propertyName, []);
        $requirement = new DataRequirement($propertyName, 'entity', $config);
        $element = ContentElementBuilder::create($entityName . '-detail')
            ->withProperty($propertyName, $propertyValue)
            ->build();

        return $loader->load($element, $requirement, Generator::generateChannelContext(), new Request());
    }

    /**
     * @param EntityCollection<Entity> $collection
     */
    private function createLoaderWithChannelRepo(
        string $entityName,
        EntityCollection $collection,
        EntityCacheTagResolver $cacheTagResolver,
    ): EntityLoader {
        $scRepo = new StaticChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $scDefRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createLoaderWithCallableRepo(string $entityName, callable $callback): EntityLoader
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

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createMinimalLoader(): EntityLoader
    {
        $scDefRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
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

    private function blogEntityLoader(): EntityLoader
    {
        return new EntityLoader(
            $this->createChannelDefinitionRegistry(new ChannelBlogDefinition()),
            $this->createDefinitionRegistry(new BlogDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    private function definitionStub(string $entityClass, string $entityName): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
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
