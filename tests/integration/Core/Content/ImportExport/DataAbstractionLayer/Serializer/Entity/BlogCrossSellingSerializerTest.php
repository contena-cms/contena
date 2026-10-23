<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\BlogCrossSellingSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingEntity;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class BlogCrossSellingSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOnlySupportsBlogCrossSelling(): void
    {
        /** @var EntityRepository<BlogCrossSellingAssignedBlogsCollection> $assignedBlogsRepository */
        $assignedBlogsRepository = static::getContainer()->get('blog_cross_selling_assigned_products.repository');

        $serializer = new BlogCrossSellingSerializer($assignedBlogsRepository);

        static::assertTrue($serializer->supports(BlogCrossSellingDefinition::ENTITY_NAME), 'should support blog cross selling');

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            if ($entity !== BlogCrossSellingDefinition::ENTITY_NAME) {
                static::assertFalse(
                    $serializer->supports($definition->getEntityName()),
                    BlogCrossSellingSerializer::class . ' should not support ' . $entity
                );
            }
        }
    }

    public function testCrossSellingSerialize(): void
    {
        $crossSelling = $this->getBlogCrossSelling();

        $assignedBlogsRepository = static::getContainer()->get('blog_cross_selling_assigned_products.repository');
        $blogCrossSellingDefinition = static::getContainer()->get(BlogCrossSellingDefinition::class);

        $serializer = new BlogCrossSellingSerializer($assignedBlogsRepository);
        $serializer->setRegistry(static::getContainer()->get(SerializerRegistry::class));

        $serialized = iterator_to_array($serializer->serialize(new Config([], [], []), $blogCrossSellingDefinition, $crossSelling));

        static::assertNotCount(0, $serialized);

        $assignedBlogs = $crossSelling->getAssignedBlogs();
        static::assertNotNull($assignedBlogs);
        $assignedBlogs->sort(static fn (BlogCrossSellingAssignedBlogsEntity $a, BlogCrossSellingAssignedBlogsEntity $b) => $a->getPosition() <=> $b->getPosition());
        $blogsIds = $assignedBlogs->map(static fn (BlogCrossSellingAssignedBlogsEntity $assignedBlogsEntity) => $assignedBlogsEntity->getBlogId());

        static::assertSame($crossSelling->getId(), $serialized['id']);
        static::assertSame($crossSelling->getBlogId(), $serialized['blogId']);
        static::assertSame(implode('|', $blogsIds), $serialized['assignedBlogs']);

        $deserialized = $serializer->deserialize(new Config([], [], []), $blogCrossSellingDefinition, $serialized);

        static::assertIsArray($deserialized);
        static::assertSame($crossSelling->getId(), $deserialized['id']);
        static::assertSame($crossSelling->getBlogId(), $deserialized['blogId']);
        static::assertSame(array_values($blogsIds), array_column($deserialized['assignedBlogs'], 'blogId'));
    }

    private function getBlogCrossSelling(): BlogCrossSellingEntity
    {
        $ids = new IdsCollection();

        $data = [
            (new BlogBuilder($ids, 'a'))->price(15, 10)->visibility()->build(),
            (new BlogBuilder($ids, 'b'))->price(15, 10)->visibility()->build(),
            (new BlogBuilder($ids, 'c'))->price(15, 10)->visibility()->build(),
            (new BlogBuilder($ids, 'd'))->price(15, 10)->visibility()->build(),
            (new BlogBuilder($ids, 'e'))->price(15, 10)->visibility()->build(),
        ];

        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create($data, Context::createDefaultContext());

        $crossSellingId = Uuid::randomHex();

        $crossSelling = [
            'id' => $crossSellingId,
            'blogId' => $ids->get('a'),
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test cross selling',
                ],
            ],
            'type' => 'blogList',
            'position' => 1,
            'limit' => 500,
            'sortBy' => 'name',
            'sortDirection' => 'ASC',
            'assignedBlogs' => [
                ['blogId' => $ids->get('b'), 'position' => 0],
                ['blogId' => $ids->get('c'), 'position' => 1],
                ['blogId' => $ids->get('d'), 'position' => 2],
                ['blogId' => $ids->get('e'), 'position' => 3],
            ],
        ];

        /** @var EntityRepository<BlogCrossSellingCollection> $crossSellingRepository */
        $crossSellingRepository = static::getContainer()->get('blog_cross_selling.repository');
        $crossSellingRepository->create([$crossSelling], Context::createDefaultContext());

        $criteria = new Criteria([$crossSellingId]);
        $criteria->addAssociation('assignedBlogs');

        $crossSellingEntity = $crossSellingRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($crossSellingEntity);

        return $crossSellingEntity;
    }
}
