<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Seo\Channel\SeoResolverData;
use Contena\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 */
#[CoversClass(SeoResolverData::class)]
class SeoResolverDataTest extends TestCase
{
    private SeoResolverData $seoResolverData;

    protected function setUp(): void
    {
        $this->seoResolverData = new SeoResolverData();
    }

    public function testGetEntitiesReturnsEmptyArrayInitially(): void
    {
        static::assertSame([], $this->seoResolverData->getEntities());
    }

    public function testAddSingleEntityAndGetEntities(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('blog', $entity);

        static::assertSame(['blog'], $this->seoResolverData->getEntities());
    }

    public function testAddMultipleEntitiesWithDifferentNames(): void
    {
        $blogEntity = $this->createMockEntity('blog-id-1');
        $categoryEntity = $this->createMockEntity('category-id-1');

        $this->seoResolverData->add('blog', $blogEntity);
        $this->seoResolverData->add('category', $categoryEntity);

        $entities = $this->seoResolverData->getEntities();
        static::assertCount(2, $entities);
        static::assertContains('blog', $entities);
        static::assertContains('category', $entities);
    }

    public function testAddSameEntityTwiceDoesNotDuplicateInEntityList(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('blog', $entity);
        $this->seoResolverData->add('blog', $entity);

        static::assertSame(['blog'], $this->seoResolverData->getEntities());
    }

    public function testGetIdsReturnsSingleIdAfterAddingEntity(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('blog', $entity);

        static::assertSame(['entity-id-1'], $this->seoResolverData->getIds('blog'));
    }

    public function testGetIdsReturnsMultipleIdsForSameEntityType(): void
    {
        $entity1 = $this->createMockEntity('entity-id-1');
        $entity2 = $this->createMockEntity('entity-id-2');

        $this->seoResolverData->add('blog', $entity1);
        $this->seoResolverData->add('blog', $entity2);

        $ids = $this->seoResolverData->getIds('blog');
        static::assertCount(2, $ids);
        static::assertContains('entity-id-1', $ids);
        static::assertContains('entity-id-2', $ids);
    }

    public function testGetIdsDoesNotReturnDuplicateIdsForSameEntity(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('blog', $entity);
        $this->seoResolverData->add('blog', $entity);

        static::assertSame(['entity-id-1'], $this->seoResolverData->getIds('blog'));
    }

    public function testGetAllReturnsSingleEntityForGivenEntityNameAndId(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('blog', $entity);

        $entities = $this->seoResolverData->getAll('blog', 'entity-id-1');
        static::assertCount(1, $entities);
        static::assertSame($entity, $entities[array_key_first($entities)]);
    }

    public function testGetAllReturnsMultipleEntitiesForSameIdButDifferentObjects(): void
    {
        $entity1 = $this->createMockEntity('entity-id-1');
        $entity2 = $this->createMockEntity('entity-id-1'); // Same ID, different object

        $this->seoResolverData->add('blog', $entity1);
        $this->seoResolverData->add('blog', $entity2);

        $entities = $this->seoResolverData->getAll('blog', 'entity-id-1');
        static::assertCount(2, $entities);
        static::assertContains($entity1, $entities);
        static::assertContains($entity2, $entities);
    }

    public function testGetAllReturnsSameEntityOnlyOnceWhenAddedMultipleTimes(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('blog', $entity);
        $this->seoResolverData->add('blog', $entity);
        $this->seoResolverData->add('blog', $entity);

        $entities = $this->seoResolverData->getAll('blog', 'entity-id-1');
        static::assertCount(1, $entities);
        static::assertSame($entity, $entities[array_key_first($entities)]);
    }

    public function testComplexScenarioWithMultipleEntitiesAndTypes(): void
    {
        $blog1 = $this->createMockEntity('blog-id-1');
        $blog2 = $this->createMockEntity('blog-id-2');
        $blog3 = $this->createMockEntity('blog-id-1'); // Same ID as blog1, different object
        $category1 = $this->createMockEntity('category-id-1');

        $this->seoResolverData->add('blog', $blog1);
        $this->seoResolverData->add('blog', $blog2);
        $this->seoResolverData->add('blog', $blog3);
        $this->seoResolverData->add('category', $category1);

        $entities = $this->seoResolverData->getEntities();
        static::assertCount(2, $entities);
        static::assertContains('blog', $entities);
        static::assertContains('category', $entities);

        $blogIds = $this->seoResolverData->getIds('blog');
        static::assertCount(2, $blogIds);
        static::assertContains('blog-id-1', $blogIds);
        static::assertContains('blog-id-2', $blogIds);

        $categoryIds = $this->seoResolverData->getIds('category');
        static::assertSame(['category-id-1'], $categoryIds);

        $entitiesForBlog1 = $this->seoResolverData->getAll('blog', 'blog-id-1');
        static::assertCount(2, $entitiesForBlog1);
        static::assertContains($blog1, $entitiesForBlog1);
        static::assertContains($blog3, $entitiesForBlog1);

        $entitiesForBlog2 = $this->seoResolverData->getAll('blog', 'blog-id-2');
        static::assertCount(1, $entitiesForBlog2);
        static::assertContains($blog2, $entitiesForBlog2);

        $entitiesForCategory1 = $this->seoResolverData->getAll('category', 'category-id-1');
        static::assertCount(1, $entitiesForCategory1);
        static::assertContains($category1, $entitiesForCategory1);
    }

    private function createMockEntity(string $uniqueIdentifier): Entity
    {
        $entity = new ChannelBlogEntity();
        $entity->setUniqueIdentifier($uniqueIdentifier);

        return $entity;
    }
}
