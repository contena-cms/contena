<?php
declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CmsSlotsDataResolver::class)]
class CmsSlotsMultiDataResolverTest extends TestCase
{
    private DefinitionInstanceRegistry $registry;

    private ExtensionDispatcher $extensions;

    protected function setUp(): void
    {
        $this->registry = new DefinitionInstanceRegistry(new ContainerBuilder(), [], []);
        $dispatcher = static::createStub(EventDispatcher::class);
        $this->extensions = new ExtensionDispatcher($dispatcher);
    }

    public function testMultipleDefinitionCriteriaWithSameHashResolveCorrectly(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'first',
                'type' => 'first',
            ]),
            (new CmsSlotEntity())->assign([
                'id' => 'slot-2',
                'slot' => 'second',
                'type' => 'second',
            ]),
        ]);

        $firstResolver = $this->createResolver('first', BlogDefinition::class);
        $secondResolver = $this->createResolver('second', CategoryDefinition::class);

        $blogDefinition = new BlogDefinition();
        $categoryDefinition = new CategoryDefinition();
        $this->registry->register($blogDefinition);
        $this->registry->register($categoryDefinition);

        $blogRepository = $this->createRepositoryMock($blogDefinition);
        $categoryRepository = $this->createRepositoryMock($categoryDefinition);

        $resolver = new CmsSlotsDataResolver(
            [$firstResolver, $secondResolver],
            ['blog' => $blogRepository, 'category' => $categoryRepository],
            $this->registry,
            $this->extensions
        );

        $context = Generator::generateChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $resolver->resolve($slots, $resolverContext);

        /** @var CmsSlotEntity $firstSlot */
        $firstSlot = $slots->getSlot('first');
        /** @var EntitySearchResult<BlogCollection> $firstData */
        $firstData = $firstSlot->getData();
        static::assertInstanceOf(EntitySearchResult::class, $firstData);
        static::assertInstanceOf(BlogCollection::class, $firstData->getEntities());

        /** @var CmsSlotEntity $secondSlot */
        $secondSlot = $slots->getSlot('second');
        /** @var EntitySearchResult<BlogCollection> $secondData */
        $secondData = $secondSlot->getData();
        static::assertInstanceOf(EntitySearchResult::class, $secondData);
        static::assertInstanceOf(CategoryCollection::class, $secondData->getEntities());
    }

    private function createResolver(string $type, string $definition): MultiCmsElementResolver
    {
        return new MultiCmsElementResolver($type, $definition);
    }

    /**
     * @return ChannelRepository<covariant EntityCollection<covariant Entity>>&Stub
     */
    private function createRepositoryMock(EntityDefinition $definition): ChannelRepository
    {
        $repository = static::createStub(ChannelRepository::class);
        $collection = new ($definition->getCollectionClass())();
        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);
        $repository->method('search')->willReturn($result);

        return $repository;
    }
}
