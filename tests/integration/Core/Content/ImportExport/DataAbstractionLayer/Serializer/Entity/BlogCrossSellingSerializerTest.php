<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsEntity;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\BlogCrossSellingSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

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

}
