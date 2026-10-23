<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturerTranslation\BlogManufacturerTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tax\TaxDefinition;

/**
 * @internal
 */
class ManyToManyAssociationFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private Context $context;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testWriteWithoutData(): void
    {
        $categoryId = Uuid::randomHex();
        $data = [
            'id' => $categoryId,
            'name' => 'test',
        ];

        $this->categoryRepository->create([$data], $this->context);

        $blogId = Uuid::randomHex();
        $data = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId],
            ],
        ];

        $writtenEvent = $this->blogRepository->create([$data], $this->context);

        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(TaxDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogManufacturerDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogCategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogManufacturerTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogTranslationDefinition::ENTITY_NAME));
        static::assertNotNull($writtenEvent->getEventByEntityName(CategoryDefinition::ENTITY_NAME));
        static::assertNull($writtenEvent->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME));
    }

    public function testWriteWithData(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $writtenEvent = $this->blogRepository->create([$data], $this->context);

        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(TaxDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(CategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogManufacturerDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogManufacturerTranslationDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogCategoryDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogDefinition::ENTITY_NAME));
        static::assertInstanceOf(EntityWrittenEvent::class, $writtenEvent->getEventByEntityName(BlogTranslationDefinition::ENTITY_NAME));
    }

    public function testReadPartialWithoutAssociationFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'blogNumber' => $id,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addFields(['name']);
        $criteria->addAssociation('properties');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $blog);
        static::assertSame('test', $blog->get('name'));
        static::assertNull($blog->get('properties'));
    }

    public function testReadPartialWithAssociationFields(): void
    {
        $id = Uuid::randomHex();
        $propertyId = Uuid::randomHex();
        $groupId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'blogNumber' => $id,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'properties' => [
                [
                    'id' => $propertyId,
                    'name' => 'Propertyname',
                    'group' => [
                        'id' => $groupId,
                        'name' => 'Groupname',
                        'customFields' => [
                            'key' => 'value',
                        ],
                    ],
                ],
            ],
            'cover' => [
                'position' => -1,
                'media' => ['fileName' => 'myFile'],
            ],
        ];

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addFields(['blogNumber', 'properties.name', 'properties.group.customFields', 'cover.media.fileName']);

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(PartialEntity::class, $blog);

        $properties = $blog->get('properties');
        static::assertInstanceOf(EntityCollection::class, $properties);

        $property = $properties->first();
        static::assertInstanceOf(PartialEntity::class, $property);

        $group = $property->get('group');
        static::assertInstanceOf(PartialEntity::class, $group);

        $cover = $blog->get('cover');
        static::assertInstanceOf(PartialEntity::class, $cover);

        $media = $cover->get('media');
        static::assertInstanceOf(PartialEntity::class, $media);

        static::assertSame($id, $blog->get('blogNumber'));
        static::assertFalse($blog->has('name'));
        static::assertFalse($blog->has('customFields'));

        static::assertSame($propertyId, $property->getId());
        static::assertSame('Propertyname', $property->get('name'));
        static::assertFalse($property->has('customFields'));

        static::assertSame($groupId, $group->getId());
        static::assertFalse($group->has('name'));
        static::assertSame('value', $group->get('customFields')['key']);

        static::assertSame('myFile', $media->get('fileName'));
    }
}
