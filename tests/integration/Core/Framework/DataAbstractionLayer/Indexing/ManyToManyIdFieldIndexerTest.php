<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ManyToManyIdFieldIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $blogPropertyRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        $this->blogPropertyRepository = static::getContainer()->get('blog_property.repository');
        $this->blogRepository = static::getContainer()->get('blog.repository');
    }

    public function testPropertyIndexing(): void
    {
        $data = new IdsCollection();

        $this->createBlog($data);

        $blog = $this->blogRepository
            ->search(new Criteria([$data->get('blog')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        $propertyIds = $blog->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertNotContains($data->create('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        $this->blogPropertyRepository->delete(
            [['blogId' => $data->get('blog'), 'optionId' => $data->get('red')]],
            Context::createDefaultContext()
        );

        $blog = $this->blogRepository
            ->search(new Criteria([$data->get('blog')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        $propertyIds = $blog->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertNotContains($data->get('red'), $propertyIds);
        static::assertNotContains($data->get('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        $this->blogPropertyRepository->create(
            [['blogId' => $data->get('blog'), 'optionId' => $data->get('red')]],
            Context::createDefaultContext()
        );

        $blog = $this->blogRepository
            ->search(new Criteria([$data->get('blog')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        $propertyIds = $blog->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertNotContains($data->get('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        $this->blogRepository->update(
            [
                [
                    'id' => $data->get('blog'),
                    'properties' => [
                        ['id' => $data->get('yellow'), 'name' => 'yellow', 'groupId' => $data->get('blog')],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $blog = $this->blogRepository
            ->search(new Criteria([$data->get('blog')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        $propertyIds = $blog->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertContains($data->get('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);
    }

    public function testResetRelation(): void
    {
        $data = new IdsCollection();

        $this->createBlog($data);

        $blog = $this->blogRepository
            ->search(new Criteria([$data->get('blog')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        // blog is created with red and green, assert both ids are inside the many to many id field
        static::assertInstanceOf(BlogEntity::class, $blog);
        $propertyIds = $blog->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertCount(2, $propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        // reset relation, the blog has now no more properties
        $this->blogPropertyRepository->delete([
            ['blogId' => $data->get('blog'), 'optionId' => $data->get('red')],
            ['blogId' => $data->get('blog'), 'optionId' => $data->get('green')],
        ], Context::createDefaultContext());

        $blog = $this->blogRepository
            ->search(new Criteria([$data->get('blog')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);

        $propertyIds = $blog->getPropertyIds();
        static::assertNull($propertyIds);

        // test re-assignment
        $this->blogPropertyRepository->create([
            ['blogId' => $data->get('blog'), 'optionId' => $data->get('red')],
            ['blogId' => $data->get('blog'), 'optionId' => $data->get('green')],
        ], Context::createDefaultContext());

        $blog = $this->blogRepository
            ->search(new Criteria([$data->get('blog')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        $propertyIds = $blog->getPropertyIds();
        static::assertIsArray($propertyIds);

        static::assertCount(2, $propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);
    }

    private function createBlog(IdsCollection $data): void
    {
        $this->blogRepository->create(
            [
                [
                    'id' => $data->create('blog'),
                    'name' => __FUNCTION__,
                    'blogNumber' => $data->get('blog'),
                    'tax' => ['name' => 'test', 'taxRate' => 15],
                    'stock' => 10,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                    ],
                    'properties' => [
                        ['id' => $data->create('red'), 'name' => 'red', 'group' => ['id' => $data->get('blog'), 'name' => 'color']],
                        ['id' => $data->create('green'), 'name' => 'green', 'groupId' => $data->get('blog')],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
