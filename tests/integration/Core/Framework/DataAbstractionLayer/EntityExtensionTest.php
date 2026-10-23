<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\FkFieldExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ModifyFieldsExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ModifyJsonFieldExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NestedDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ReferenceVersionExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class EntityExtensionTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        parent::tearDown();
        static::getContainer()->get(BlogDefinition::class)->getFields()->remove('myPrices');
        static::getContainer()->get(BlogDefinition::class)->getFields()->remove('myCategories');
    }

    public function testICanWriteAndReadManyToOneAssociationExtension(): void
    {
        $this->connection->rollBack();

        try {
            $this->connection->executeStatement('ALTER TABLE `blog` ADD COLUMN my_tax_id binary(16) NULL');
        } catch (Exception) {
        }

        $this->connection->beginTransaction();

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField(
            (new ManyToOneAssociationField('myTax', 'my_tax_id', TaxDefinition::class, 'id'))->addFlags(new ApiAware(), new Extension())
        );
        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField(
            (new FkField('my_tax_id', 'myTaxId', TaxDefinition::class))->addFlags(new ApiAware(), new Extension())
        );

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
            'myTax' => ['id' => $id, 'name' => 'my-tax', 'taxRate' => 50],
        ];

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myTax');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);

        static::assertTrue($blog->hasExtension('myTax'));

        $tax = $blog->getExtension('myTax');
        static::assertInstanceOf(TaxEntity::class, $tax);

        static::assertSame('my-tax', $tax->getName());

        $this->connection->rollBack();

        $this->connection->executeStatement('ALTER TABLE `blog` DROP COLUMN my_tax_id');

        $this->connection->beginTransaction();

        static::getContainer()->get(BlogDefinition::class)->getFields()->remove('myTax');
        static::getContainer()->get(BlogDefinition::class)->getFields()->remove('myTaxId');
    }

    public function testICanWriteOneToManyAssociationsExtensions(): void
    {
        $field = (new OneToManyAssociationField('myPrices', BlogPriceDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_price WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $data['extensions']['myPrices'] = $data['myPrices'];
        unset($data['myPrices']);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_price WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);
    }

    public function testICanReadOneToManyAssociationsExtensionsInBasic(): void
    {
        $field = (new OneToManyAssociationField('myPrices', BlogPriceDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myPrices');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myPrices'));
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getExtension('myPrices'));
        static::assertCount(2, $blog->getExtension('myPrices'));
    }

    public function testICanReadOneToManyAssociationsExtensionsNotInBasic(): void
    {
        $field = (new OneToManyAssociationField('myPrices', BlogPriceDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $blog = $this->blogRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myPrices'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myPrices');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myPrices'));
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getExtension('myPrices'));
        static::assertCount(2, $blog->getExtension('myPrices'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('extensions.myPrices');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myPrices'));
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getExtension('myPrices'));
        static::assertCount(2, $blog->getExtension('myPrices'));
    }

    public function testICanSearchOneToManyAssociationsExtensions(): void
    {
        $field = (new OneToManyAssociationField('myPrices', BlogPriceDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.myPrices.price.gross', 15));
        $criteria->addFilter(new EqualsFilter('blog.ean', 'test'));

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myPrices'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.extensions.myPrices.price.gross', 15));
        $criteria->addFilter(new EqualsFilter('blog.ean', 'test'));

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myPrices'));
    }

    public function testICanReadPaginatedOneToManyAssociationsExtensions(): void
    {
        $field = (new OneToManyAssociationField('myPrices', BlogPriceDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $blog = $this->blogRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myPrices'));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('myPrices')->setLimit(1);

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myPrices'));
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getExtension('myPrices'));
        static::assertCount(1, $blog->getExtension('myPrices'));
    }

    public function testICanWriteManyToManyAssociationsExtensions(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            BlogCategoryDefinition::class,
            'blog_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $data['extensions']['myCategories'] = $data['myCategories'];
        unset($data['myCategories']);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);
    }

    public function testICanReadManyToManyAssociationsExtensionsInBasic(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            BlogCategoryDefinition::class,
            'blog_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $blog->getExtension('myCategories'));
        static::assertCount(2, $blog->getExtension('myCategories'));
    }

    public function testICanReadManyToManyAssociationsExtensionsNotInBasic(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            BlogCategoryDefinition::class,
            'blog_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $blog = $this->blogRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $blog->getExtension('myCategories'));
        static::assertCount(2, $blog->getExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $blog->getExtension('myCategories'));
        static::assertCount(2, $blog->getExtension('myCategories'));
    }

    public function testICanSearchManyToManyAssociationsExtensions(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            BlogCategoryDefinition::class,
            'blog_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.myCategories.level', 1));
        $criteria->addFilter(new EqualsFilter('blog.myCategories.name', 'test'));

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myCategories'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.extensions.myCategories.level', 1));
        $criteria->addFilter(new EqualsFilter('blog.myCategories.name', 'test'));

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myCategories'));
    }

    public function testICanReadPaginatedManyToManyAssociationsExtensions(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            BlogCategoryDefinition::class,
            'blog_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        static::getContainer()->get(BlogDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->blogRepository->create([$data], Context::createDefaultContext());

        $blog = $this->blogRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->hasExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('extensions.myCategories')->setLimit(2);

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $blog->getExtension('myCategories'));
        static::assertCount(2, $blog->getExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories')->setLimit(2);

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $blog->getExtension('myCategories'));
        static::assertCount(2, $blog->getExtension('myCategories'));
    }

    public function testICantAddScalarExtensions(): void
    {
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ScalarExtension::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);

        $this->expectExceptionObject(DataAbstractionLayerException::wrongFieldTypeForExtension());

        $definition->getFields()->has('test');
    }

    public function testICanAddRuntimeExtensions(): void
    {
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ScalarRuntimeExtension::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);
        static::assertTrue($definition->getFields()->has('test'));
    }

    public function testICanAddFkFieldsAsExtensions(): void
    {
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, FkFieldExtension::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);
        static::assertTrue($definition->getFields()->has('test'));
    }

    public function testICanAddAssociationExtensions(): void
    {
        $this->registerDefinition(ExtendedDefinition::class);
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, AssociationExtension::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);
        static::assertTrue($definition->getFields()->has('toOne'));
        static::assertTrue($definition->getFields()->has('toMany'));
    }

    public function testICanAddReferenceVersionAsExtensionWithValidManyToOneAssociation(): void
    {
        $this->registerDefinition(ExtendedDefinition::class);
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ReferenceVersionExtension::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);
        static::assertTrue($definition->getFields()->has('toOne'));
        static::assertTrue($definition->getFields()->has('extendedVersionId'));
    }

    public function testICanModifyFields(): void
    {
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);
        $field = $definition->getFields()->get('apiAwareTest');
        static::assertInstanceOf(BoolField::class, $field);
        static::assertTrue($field->is(ApiAware::class), 'Field ' . $field->getPropertyName() . ' should have ApiAware flag');

        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ModifyFieldsExtension::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);
        $field = $definition->getFields()->get('apiAwareTest');
        static::assertInstanceOf(BoolField::class, $field);
        static::assertFalse($field->is(ApiAware::class), 'Field ' . $field->getPropertyName() . ' should not have ApiAware flag');
    }

    public function testICantAddOrRemoveFieldsByModifyFields(): void
    {
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ModifyFieldsExtension::class);
        $definition = static::getContainer()->get(ExtendableDefinition::class);

        static::assertInstanceOf(ExtendableDefinition::class, $definition);
        // Should only contain "id", "api_aware_test", "created_at", "updated_at"
        static::assertCount(4, $definition->getFields(), 'ModifyFieldsExtension should not be able to add or remove fields');
    }

    public function testICanAddJsonPropertyMappingByModifyFields(): void
    {
        $definition = $this->registerDefinition(NestedDefinition::class);
        $data = $definition->getFields()->get('data');
        static::assertInstanceOf(JsonField::class, $data);
        static::assertCount(3, $data->getPropertyMapping());

        $definition = $this->registerDefinitionWithExtensions(NestedDefinition::class, ModifyJsonFieldExtension::class);
        $data = $definition->getFields()->get('data');
        static::assertInstanceOf(JsonField::class, $data);

        $propertyNames = array_map(
            static fn (Field $field) => $field->getPropertyName(),
            $data->getPropertyMapping()
        );
        static::assertSame(['gross', 'net', 'foo', 'extended'], $propertyNames);
    }

    /**
     * @return array{id:string, blogNumber:string, stock:int, name:string, ean:string, price:array{array{currencyId:string, gross:int, net:int, linked:bool}}, manufacturer:array{name:string}, tax:array{name:string, taxRate:int}, myPrices:list<array{id:string, currencyId:string, quantityStart:int, ruleId:string, price:array{array{currencyId:string, gross:int, net:int, linked:bool}}}>}
     */
    private function getPricesData(string $id): array
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create(
            [
                ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
                ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
            ],
            Context::createDefaultContext()
        );

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'ean' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'myPrices' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        return $data;
    }

    /**
     * @return array{id:string, blogNumber:string, stock:int, name:string, ean:string, price:array{array{currencyId:string, gross:int, net:int, linked:bool}}, manufacturer:array{name:string}, tax:array{name:string, taxRate:int}, myCategories:list<array{id:string}>}
     */
    private function getCategoriesData(string $id): array
    {
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        static::getContainer()->get('category.repository')->create(
            [
                ['id' => $categoryA, 'name' => 'test', 'position' => 0, 'level' => 1],
                ['id' => $categoryB, 'name' => 'test', 'position' => 1, 'level' => 2],
            ],
            Context::createDefaultContext()
        );

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'category test',
            'ean' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'myCategories' => [
                [
                    'id' => $categoryA,
                ],
                [
                    'id' => $categoryB,
                ],
            ],
        ];

        return $data;
    }
}
