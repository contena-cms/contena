<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceEntity;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordCollection;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Exception\DuplicateBlogNumberException;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\ParentRelationValidator;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    final public const TEST_LANGUAGE_ID = 'cc72c24b82684d72a4ce91054da264bf';
    final public const TEST_LOCALE_ID = 'cf735c44dc7b4428bb3870fe4ffea2df';
    final public const TEST_LANGUAGE_LOCALE_CODE = 'sw-AG';

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testWritePrice(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        $blog = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($blog);

        static::assertEquals(
            new Price(Defaults::CURRENCY, 10, 15, false),
            $blog->getPrice()?->getCurrencyPrice(Defaults::CURRENCY)
        );
    }

    public function testWriteMultipleCurrencyPrices(): void
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('currency.repository')->create(
            [
                [
                    'id' => $id,
                    'factor' => 2,
                    'shortName' => 'test',
                    'name' => 'name',
                    'symbol' => 'A',
                    'isoCode' => 'XX',
                    'decimalPrecision' => 2,
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                ],
            ],
            $this->context
        );

        $data = [
            'id' => $id,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ['currencyId' => $id, 'gross' => 150, 'net' => 100, 'linked' => true],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        $blog = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($blog);
        static::assertNotNull($blog->getPrice());

        static::assertCount(2, $blog->getPrice());

        static::assertEquals(
            new Price(Defaults::CURRENCY, 10, 15, false),
            $blog->getCurrencyPrice(Defaults::CURRENCY)
        );

        static::assertEquals(
            new Price($id, 100, 150, true),
            $blog->getCurrencyPrice($id)
        );
    }

    public function testVariantNameIsNullable(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                // name should be required
                'name' => 'parent',
            ],
            [
                'id' => $variantId,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 15,
            ],
        ];

        try {
            $this->repository->create($blogs, $this->context);

            $update = ['name' => null, 'id' => $variantId];

            $this->repository->update([$update], $this->context);
        } catch (\Exception) {
            static::fail('Can not reset variant name to null');
        }

        $variant = $this->repository->search(new Criteria([$variantId]), $this->context)->getEntities()->first();

        static::assertNotNull($variant);
        static::assertNull($variant->getName());
    }

    public function testUpdatedBlogChildCountOnVariantDeletion(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $secondVariantId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                // name should be required
                'name' => 'parent',
            ],
            [
                'id' => $variantId,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 15,
            ],
            [
                'id' => $secondVariantId,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 15,
            ],
        ];

        $this->repository->create($blogs, $this->context);
        $delete = $this->repository->delete([['id' => $secondVariantId]], $this->context);

        $this->runWorker();

        $ids = $delete->getPrimaryKeys('blog');

        static::assertContains($secondVariantId, $ids);

        $this->runWorker();

        $blog = $this->repository->search(new Criteria([$parentId]), $this->context)->getEntities()->first();
        static::assertNotNull($blog);
        static::assertSame(1, $blog->getChildCount());
    }

    public function testNameIsRequiredForParent(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $e = null;

        try {
            $this->repository->create([$data], $this->context);
        } catch (WriteException $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];
        $this->repository->create([$data], $this->context);

        $blog = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($blog);

        $variantId = Uuid::randomHex();

        $data = [
            'id' => $variantId,
            'stock' => 10,
            'blogNumber' => 'variant',
            'parentId' => $id,
        ];
        $this->repository->create([$data], $this->context);

        $variant = $this->repository->search(new Criteria([$variantId]), $this->context)->getEntities()->get($variantId);
        static::assertNotNull($variant);
        static::assertNull($variant->getName());
    }

    /**
     * @param array<string, mixed> $relationData
     */
    #[DataProvider('dataProviderSelfReferences')]
    public function testParentCanNotReferenceItself(string $id, array $relationData): void
    {
        $data = [
            'id' => $id,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];
        $this->repository->create([$data], $this->context);

        $e = null;

        try {
            $this->repository->update([$relationData], $this->context);
        } catch (WriteException $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);
        $exception = $e->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        $violation = $exception->getViolations()->get(0);
        static::assertSame(ParentRelationValidator::VIOLATION_PARENT_RELATION_DOES_NOT_ALLOW_SELF_REFERENCES, $violation->getCode());
    }

    public static function dataProviderSelfReferences(): \Generator
    {
        $id = Uuid::randomHex();
        yield 'over parent FK field' => [$id, ['id' => $id, 'parentId' => $id]];

        yield 'over parent association' => [$id, ['id' => $id, 'parent' => ['id' => $id]]];

        yield 'over children association' => [$id, ['id' => $id, 'children' => [['id' => $id]]]];
    }

    public function testSearchKeywordIndexerConsidersUpdate(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Default name',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('searchKeywords');

        $blog = $this->repository->search($criteria, $this->context)->getEntities()->get($id);
        static::assertNotNull($blog);
        static::assertInstanceOf(BlogSearchKeywordCollection::class, $blog->getSearchKeywords());

        $keywords = $blog->getSearchKeywords()->map(static fn (BlogSearchKeywordEntity $entity) => $entity->getKeyword());

        static::assertContains('default', $keywords);
        static::assertContains('name', $keywords);

        $update = [
            'id' => $id,
            'name' => 'updated',
        ];

        $this->repository->update([$update], $this->context);

        $blog = $this->repository->search($criteria, $this->context)->getEntities()->get($id);
        static::assertNotNull($blog);

        static::assertInstanceOf(BlogSearchKeywordCollection::class, $blog->getSearchKeywords());

        $keywords = $blog->getSearchKeywords()->map(static fn (BlogSearchKeywordEntity $entity) => $entity->getKeyword());

        static::assertNotContains('default', $keywords);
        static::assertNotContains('name', $keywords);
        static::assertContains('updated', $keywords);
    }

    #[DataProvider('dataProviderBlogWrite')]
    public function testWriteBlogType(?string $type, string $expectedType): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Default name',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'type' => $type,
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        if ($type === 'unset') {
            unset($data['type']);
        }

        $this->repository->create([$data], $this->context);

        $criteria = new Criteria([$id]);
        $blog = $this->repository->search($criteria, $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame($expectedType, $blog->getType());
    }

    public static function dataProviderBlogWrite(): \Generator
    {
        yield 'no type provided' => ['unset', BlogDefinition::TYPE_PHYSICAL];
        yield 'default blog type provided' => [BlogDefinition::TYPE_PHYSICAL, BlogDefinition::TYPE_PHYSICAL];
        yield 'digital blog type provided' => [BlogDefinition::TYPE_DIGITAL, BlogDefinition::TYPE_DIGITAL];
    }

    public function testWriteCategories(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $this->repository->create([$data], $this->context);

        $record = $this->connection->fetchAssociative('SELECT * FROM blog_category WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotFalse($record);
        static::assertSame($record['blog_id'], Uuid::fromHexToBytes($id));
        static::assertSame($record['category_id'], Uuid::fromHexToBytes($id));

        $record = $this->connection->fetchAssociative('SELECT * FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotFalse($record);
    }

    public function testWriteBlogWithDifferentTaxFormat(): void
    {
        $tax = Uuid::randomHex();

        $data = [
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'without id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'taxId' => $tax,
            ],
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'taxRate' => 18],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $criteria = new Criteria($ids);
        $criteria->addAssociation('tax');
        $blogs = $this->repository->search($criteria, $this->context)->getEntities();

        $blog = $blogs->get($ids[0]);
        static::assertNotNull($blog);
        static::assertInstanceOf(TaxEntity::class, $blog->getTax());
        static::assertSame('without id', $blog->getTax()->getName());
        static::assertSame(19.0, $blog->getTax()->getTaxRate());

        $blog = $blogs->get($ids[1]);
        static::assertNotNull($blog);
        static::assertInstanceOf(TaxEntity::class, $blog->getTax());
        static::assertSame($tax, $blog->getTaxId());
        static::assertSame($tax, $blog->getTax()->getId());
        static::assertSame('with id', $blog->getTax()->getName());
        static::assertSame(18.0, $blog->getTax()->getTaxRate());

        $blog = $blogs->get($ids[2]);
        static::assertNotNull($blog);
        static::assertInstanceOf(TaxEntity::class, $blog->getTax());
        static::assertSame($tax, $blog->getTaxId());
        static::assertSame($tax, $blog->getTax()->getId());
        static::assertSame('with id', $blog->getTax()->getName());
        static::assertSame(18.0, $blog->getTax()->getTaxRate());

        $blog = $blogs->get($ids[3]);
        static::assertNotNull($blog);
        static::assertInstanceOf(TaxEntity::class, $blog->getTax());
        static::assertSame($tax, $blog->getTaxId());
        static::assertSame($tax, $blog->getTax()->getId());
        static::assertSame('with id', $blog->getTax()->getName());
        static::assertSame(18.0, $blog->getTax()->getTaxRate());
    }

    public function testWriteBlogWithDifferentManufacturerStructures(): void
    {
        $manufacturerId = Uuid::randomHex();

        $data = [
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['name' => 'without id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturerId' => $manufacturerId,
            ],
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'link' => 'test'],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $criteria = new Criteria($ids);
        $criteria->addAssociation('manufacturer');

        $blogs = $this->repository->search($criteria, $this->context)->getEntities();

        $blog = $blogs->get($ids[0]);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogManufacturerEntity::class, $blog->getManufacturer());
        static::assertSame('without id', $blog->getManufacturer()->getName());

        $blog = $blogs->get($ids[1]);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogManufacturerEntity::class, $blog->getManufacturer());
        static::assertSame($manufacturerId, $blog->getManufacturerId());
        static::assertSame($manufacturerId, $blog->getManufacturer()->getId());
        static::assertSame('with id', $blog->getManufacturer()->getName());

        $blog = $blogs->get($ids[2]);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogManufacturerEntity::class, $blog->getManufacturer());
        static::assertSame($manufacturerId, $blog->getManufacturerId());
        static::assertSame($manufacturerId, $blog->getManufacturer()->getId());
        static::assertSame('with id', $blog->getManufacturer()->getName());

        $blog = $blogs->get($ids[2]);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogManufacturerEntity::class, $blog->getManufacturer());
        static::assertSame($manufacturerId, $blog->getManufacturerId());
        static::assertSame($manufacturerId, $blog->getManufacturer()->getId());
        static::assertSame('with id', $blog->getManufacturer()->getName());
        static::assertSame('test', $blog->getManufacturer()->getLink());
    }

    public function testReadAndWriteOfBlogManufacturerAssociation(): void
    {
        $id = Uuid::randomHex();

        // check nested events are triggered
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->exactly(2))->method('__invoke');
        EventHookDispatcher::fromContainer(static::getContainer())->on('blog.written', $listener);
        EventHookDispatcher::fromContainer(static::getContainer())->on('blog_manufacturer.written', $listener);

        $this->repository->create([
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ], Context::createDefaultContext());

        // validate that nested events are triggered
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->exactly(2))->method('__invoke');
        EventHookDispatcher::fromContainer(static::getContainer())->on('blog.loaded', $listener);
        EventHookDispatcher::fromContainer(static::getContainer())->on('blog_manufacturer.loaded', $listener);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('manufacturer');

        $blogs = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();

        // check only provided id loaded
        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        // check data loading is as expected
        static::assertSame($id, $blog->getId());
        static::assertSame('Test', $blog->getName());

        static::assertInstanceOf(BlogManufacturerEntity::class, $blog->getManufacturer());

        // check nested element loaded
        $manufacturer = $blog->getManufacturer();
        static::assertSame('test', $manufacturer->getName());
    }

    public function testReadAndWriteBlogPrices(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        static::assertSame($id, $blog->getId());

        static::assertEquals(new Price(Defaults::CURRENCY, 10, 15, false), $blog->getCurrencyPrice(Defaults::CURRENCY));
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());

        $price = $blog->getPrices()->get($ruleA);
        static::assertInstanceOf(BlogPriceEntity::class, $price);
        $currencyPrice = $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(15.0, $currencyPrice->getGross());
        static::assertSame(10.0, $currencyPrice->getNet());

        $price = $blog->getPrices()->get($ruleB);
        static::assertInstanceOf(BlogPriceEntity::class, $price);
        $currencyPrice = $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(10.0, $currencyPrice->getGross());
        static::assertSame(8.0, $currencyPrice->getNet());
    }

    public function testBlogPricesSortByGrossPrice(): void
    {
        $context = Context::createDefaultContext();
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], $context);

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 1,
                    'quantityEnd' => 10,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 13, 'linked' => false]],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 11,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->create([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();

        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        static::assertSame($id, $blog->getId());

        static::assertEquals(new Price(Defaults::CURRENCY, 10, 15, false), $blog->getCurrencyPrice(Defaults::CURRENCY));
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());

        $blog->getPrices()->sortByPrice($context);

        $price = $blog->getPrices()->first();
        static::assertNotNull($price);
        $firstPrice = $price->getPrice()->first();
        static::assertNotNull($firstPrice);
        static::assertSame(10.0, $firstPrice->getGross());
        static::assertSame(8.0, $firstPrice->getNet());
    }

    public function testBlogPricesSortByNetPrice(): void
    {
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], $context);

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 1,
                    'quantityEnd' => 10,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 13, 'linked' => false]],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 11,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 19, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->create([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        static::assertSame($id, $blog->getId());

        static::assertEquals(new Price(Defaults::CURRENCY, 10, 15, false), $blog->getCurrencyPrice(Defaults::CURRENCY));
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());

        $blog->getPrices()->sortByPrice($context);

        $price = $blog->getPrices()->first();
        static::assertNotNull($price);
        $firstPrice = $price->getPrice()->first();
        static::assertNotNull($firstPrice);
        static::assertSame(19.0, $firstPrice->getGross());
        static::assertSame(8.0, $firstPrice->getNet());
    }

    public function testPriceRulesSorting(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $ruleA = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
        ], Context::createDefaultContext());

        $filterId = Uuid::randomHex();

        $data = [
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'price test 1',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 500, 'net' => 400, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 14, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $id2,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'price test 2',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 500, 'net' => 400, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 4, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $id3,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'price test 3',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 500, 'net' => 400, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('blog.prices.price', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('blog.ean', $filterId));

        $context = $this->createContext([$ruleA]);

        $blogs = $this->repository->searchIds($criteria, $context);

        static::assertSame(
            [$id2, $id3, $id],
            $blogs->getIds()
        );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('blog.prices.price', FieldSorting::DESCENDING));
        $criteria->addFilter(new EqualsFilter('blog.ean', $filterId));

        $blogs = $this->repository->searchIds($criteria, $context);

        static::assertSame(
            [$id, $id3, $id2],
            $blogs->getIds()
        );
    }

    public function testVariantInheritancePriceAndName(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10.0, 'net' => 9, 'linked' => true];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 15.0, 'net' => 14, 'linked' => true];

        $redName = 'Red shirt';

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $parentName,
                'price' => [$parentPrice],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
            ],

            // price should be inherited
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            // name should be inherited
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $this->repository->create($blogs, $context);

        $criteria = new Criteria([$redId, $greenId]);
        $blogs = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $parents = $this->repository->search($criteria, $context)->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($blogs->has($redId));
        static::assertTrue($blogs->has($greenId));

        $parent = $parents->get($parentId);
        $red = $blogs->get($redId);
        $green = $blogs->get($greenId);

        $parentCurrencyPrice = $parent->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $parentCurrencyPrice);
        static::assertSame($parentPrice['gross'], $parentCurrencyPrice->getGross());
        static::assertSame($parentName, $parent->getName());

        $redCurrencyPrice = $red->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $redCurrencyPrice);
        static::assertSame($parentPrice['gross'], $redCurrencyPrice->getGross());
        static::assertSame($redName, $red->getName());

        $greenCurrencyPrice = $green->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $greenCurrencyPrice);
        static::assertSame($greenPrice['gross'], $greenCurrencyPrice->getGross());
        static::assertSame($parentName, $green->getTranslated()['name']);
        static::assertNull($green->getName());

        /** @var array{price: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price` FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertEquals(['c' . Defaults::CURRENCY => $parentPrice], json_decode($row['price'], true, 512, \JSON_THROW_ON_ERROR));

        /** @var array{name: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `name` FROM blog_translation WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertSame($parentName, $row['name']);

        /** @var array{price: string|null} $row */
        $row = $this->connection->fetchAssociative('SELECT `price` FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertNull($row['price']);

        /** @var array{name: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `name` FROM blog_translation WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertSame($redName, $row['name']);

        /** @var array{price: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price` FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertEquals(['c' . Defaults::CURRENCY => $greenPrice], json_decode($row['price'], true, 512, \JSON_THROW_ON_ERROR));

        $row = $this->connection->fetchAssociative('SELECT * FROM blog_translation WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertFalse($row);
    }

    public function testInsertAndUpdateInOneStep(): void
    {
        $id = Uuid::randomHex();
        $filterId = Uuid::randomHex();
        $data = [
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Insert',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'test'],
                'ean' => $filterId,
            ],
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Update',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 10, 'linked' => false]],
                'ean' => $filterId,
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        $blogs = $this->repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities();
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        static::assertSame('Update', $blog->getName());
        $currencyPrice = $blog->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(12.0, $currencyPrice->getGross());

        $count = (int) $this->connection->fetchOne('SELECT COUNT(id) FROM blog WHERE ean = :filterId', ['filterId' => $filterId]);
        static::assertSame(1, $count);
    }

    public function testSwitchVariantToFullBlog(): void
    {
        $id = Uuid::randomHex();
        $child = Uuid::randomHex();

        $filterId = Uuid::randomHex();
        $data = [
            ['id' => $id, 'blogNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'Insert', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 10], 'manufacturer' => ['name' => 'test'], 'ean' => $filterId],
            ['id' => $child, 'blogNumber' => Uuid::randomHex(), 'stock' => 10, 'parentId' => $id, 'name' => 'Update', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false]], 'ean' => $filterId],
        ];
        $this->repository->upsert($data, Context::createDefaultContext());

        $blogs = $this->repository->search(new Criteria([$id, $child]), Context::createDefaultContext())->getEntities();
        static::assertTrue($blogs->has($id));
        static::assertTrue($blogs->has($child));

        $raw = $this->connection->fetchAllAssociative('SELECT * FROM blog WHERE ean = :filterId', ['filterId' => $filterId]);
        static::assertCount(2, $raw);

        $name = $this->connection->fetchOne('SELECT name FROM blog_translation WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($child)]);
        static::assertSame('Update', $name);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
            ],
        ];

        $e = null;

        try {
            $this->repository->upsert($data, Context::createDefaultContext());
        } catch (\Exception $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);

        $constraintViolation = $e->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $constraintViolation);

        static::assertSame('/taxId', $constraintViolation->getViolations()->get(0)->getPropertyPath());

        $data = [
            [
                'id' => $child,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => null,
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'name' => 'Child transformed to parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 13, 'net' => 12, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        /** @var array{parent_id: string|null} $raw */
        $raw = $this->connection->fetchAssociative('SELECT `parent_id` FROM blog WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $criteria = new Criteria([$child]);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('tax');
        $blogs = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertTrue($blogs->has($child));
        $blog = $blogs->get($child);

        static::assertSame('Child transformed to parent', $blog->getName());
        $currencyPrice = $blog->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(13.0, $currencyPrice->getGross());
        $manufacturer = $blog->getManufacturer();
        static::assertInstanceOf(BlogManufacturerEntity::class, $manufacturer);
        static::assertSame('test3', $manufacturer->getName());
        $tax = $blog->getTax();
        static::assertInstanceOf(TaxEntity::class, $tax);
        static::assertSame(15.0, $tax->getTaxRate());
    }

    public function testSwitchVariantToFullBlogWithoutName(): void
    {
        $id = Uuid::randomHex();
        $child = Uuid::randomHex();

        $data = [
            [
                'id' => $id,
                'name' => 'Insert',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'test'],
                'stock' => 1,
                'blogNumber' => 'SW100',
            ],
            [
                'id' => $child,
                'parentId' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false]],
                'stock' => 2,
                'blogNumber' => 'SW100.1',
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        $blogs = $this->repository->search(new Criteria([$id, $child]), Context::createDefaultContext())->getEntities();
        static::assertTrue($blogs->has($id));
        static::assertTrue($blogs->has($child));

        $raw = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id, $child])],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertCount(2, $raw);

        $name = $this->connection->fetchOne('SELECT name FROM blog_translation WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($child)]);
        static::assertFalse($name);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
            ],
        ];

        $e = null;

        try {
            $this->repository->upsert($data, Context::createDefaultContext());
        } catch (\Exception $e) {
        }
        static::assertInstanceOf(WriteException::class, $e);

        $message = $e->getMessage();

        static::assertStringContainsString('/0/taxId', $message);
        static::assertStringContainsString('/0/stock', $message);
        static::assertStringContainsString('/0/price', $message);
        static::assertStringContainsString('/0/blogNumber', $message);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'name' => 'Child transformed to parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 13, 'net' => 12, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
                'stock' => 3,
                'blogNumber' => 'SW200',
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        /** @var array{parent_id: string|null} $raw */
        $raw = $this->connection->fetchAssociative('SELECT `parent_id` FROM blog WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $criteria = new Criteria([$child]);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('tax');

        $blogs = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertTrue($blogs->has($child));
        $blog = $blogs->get($child);

        $currencyPrice = $blog->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(13.0, $currencyPrice->getGross());
        $manufacturer = $blog->getManufacturer();
        static::assertInstanceOf(BlogManufacturerEntity::class, $manufacturer);
        static::assertSame('test3', $manufacturer->getName());
        $tax = $blog->getTax();
        static::assertInstanceOf(TaxEntity::class, $tax);
        static::assertSame(15.0, $tax->getTaxRate());
        static::assertSame('SW200', $blog->getBlogNumber());
        static::assertSame('Child transformed to parent', $blog->getName());
    }

    public function testVariantInheritanceWithTax(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentTaxId = Uuid::randomHex();
        $greenTaxId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'tax' => ['id' => $parentTaxId, 'taxRate' => 13, 'name' => 'green'],
            ],

            // price should be inherited
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
            ],

            // name should be inherited
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'tax' => [
                    'id' => $greenTaxId,
                    'taxRate' => 13,
                    'name' => 'green',
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($blogs, $context);

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('tax');
        $blogs = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('tax');
        $context->setConsiderInheritance(false);
        $parents = $this->repository->search($criteria, $context)->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($blogs->has($redId));
        static::assertTrue($blogs->has($greenId));

        $parent = $parents->get($parentId);
        $red = $blogs->get($redId);
        $green = $blogs->get($greenId);

        $parentTax = $parent->getTax();
        static::assertInstanceOf(TaxEntity::class, $parentTax);
        static::assertSame($parentTaxId, $parentTax->getId());
        $redTax = $red->getTax();
        static::assertInstanceOf(TaxEntity::class, $redTax);
        static::assertSame($parentTaxId, $redTax->getId());
        $greenTax = $green->getTax();
        static::assertInstanceOf(TaxEntity::class, $greenTax);
        static::assertSame($greenTaxId, $greenTax->getId());

        static::assertSame($parentTaxId, $parent->getTaxId());
        static::assertSame($parentTaxId, $red->getTaxId());
        static::assertSame($greenTaxId, $green->getTaxId());

        /** @var array{price: string, tax_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price`, `tax_id` FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);

        static::assertEquals(
            [
                'c' . Defaults::CURRENCY => ['net' => 9, 'gross' => 10, 'linked' => true, 'currencyId' => Defaults::CURRENCY],
            ],
            json_decode($row['price'], true, 512, \JSON_THROW_ON_ERROR)
        );
        static::assertSame($parentTaxId, Uuid::fromBytesToHex($row['tax_id']));

        /** @var array{price: string|null, tax_id: string|null} $row */
        $row = $this->connection->fetchAssociative('SELECT `price`, `tax_id` FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertNull($row['price']);
        static::assertNull($row['tax_id']);

        /** @var array{price: string|null, tax_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price`, `tax_id` FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertNull($row['price']);
        static::assertSame($greenTaxId, Uuid::fromBytesToHex($row['tax_id']));

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('tax');
        $context->setConsiderInheritance(false);
        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertTrue($blogs->has($redId));
        $red = $blogs->get($redId);
        static::assertNull($red->getTax());
    }

    public function testWriteBlogWithSameTaxes(): void
    {
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];
        $price = [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]];
        $data = [
            ['blogNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['blogNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['blogNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['blogNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['blogNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
        ];

        $taxes = $this->repository->create($data, Context::createDefaultContext())->getEventByEntityName(TaxDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $taxes);
        static::assertCount(1, array_unique($taxes->getIds()));
    }

    public function testBlogMediaAssociationWithSortingAndPagination(): void
    {
        $id = Uuid::randomHex();
        $a = Uuid::randomHex();
        $b = Uuid::randomHex();
        $c = Uuid::randomHex();
        $d = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => $id,
            'name' => 'T-shirt',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 10,
            'media' => [
                ['id' => $d, 'position' => 4, 'media' => ['fileName' => 'd']],
                ['id' => $b, 'position' => 2, 'media' => ['fileName' => 'b']],
                ['id' => $a, 'position' => 1, 'media' => ['fileName' => 'a']],
                ['id' => $c, 'position' => 3, 'media' => ['fileName' => 'c']],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('media')
            ->setLimit(3)
            ->addSorting(new FieldSorting('blog_media.position', FieldSorting::ASCENDING));

        $blog = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($blog);
        $blogMedia = $blog->getMedia();
        static::assertNotNull($blogMedia);

        $ids = $blogMedia->map(static fn (BlogMediaEntity $a) => $a->getId());

        $order = [$a, $b, $c];
        static::assertSame($order, array_values($ids));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('media')
            ->setLimit(3)
            ->addSorting(new FieldSorting('blog_media.position', FieldSorting::DESCENDING));

        $blog = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($blog);
        $blogMedia = $blog->getMedia();
        static::assertNotNull($blogMedia);

        $ids = $blogMedia->map(static fn (BlogMediaEntity $a) => $a->getId());

        $order = [$d, $c, $b];
        static::assertSame($order, array_values($ids));
    }

    public function testVariantInheritanceWithMedia(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentMediaId = Uuid::randomHex();
        $greenMediaId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
                'media' => [
                    [
                        'id' => $parentMediaId,
                        'media' => [
                            'id' => $parentMediaId,
                            'name' => 'test file',
                        ],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'name' => 'red',
                'stock' => 10,
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 10,
                'name' => 'green',
                'media' => [
                    [
                        'id' => $greenMediaId,
                        'media' => [
                            'id' => $greenMediaId,
                            'name' => 'test file',
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create($blogs, Context::createDefaultContext());

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('media');

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $blogs = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('media');

        $parents = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($blogs->has($redId));
        static::assertTrue($blogs->has($greenId));

        $parent = $parents->get($parentId);
        $green = $blogs->get($greenId);
        $red = $blogs->get($redId);

        $parentMedia = $parent->getMedia();
        static::assertInstanceOf(BlogMediaCollection::class, $parentMedia);
        static::assertCount(1, $parentMedia);
        static::assertTrue($parentMedia->has($parentMediaId));

        $greenMedia = $green->getMedia();
        static::assertInstanceOf(BlogMediaCollection::class, $greenMedia);
        static::assertCount(1, $greenMedia);
        static::assertTrue($greenMedia->has($greenMediaId));

        $redMedia = $red->getMedia();
        static::assertInstanceOf(BlogMediaCollection::class, $redMedia);
        static::assertCount(1, $redMedia);
        static::assertTrue($redMedia->has($parentMediaId));

        /** @var array{media_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT media_id FROM blog_media WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertSame($parentMediaId, Uuid::fromBytesToHex($row['media_id']));

        /** @var array{media_id: string}|false $row */
        $row = $this->connection->fetchAssociative('SELECT media_id FROM blog_media WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertFalse($row);

        /** @var array{media_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT media_id FROM blog_media WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertSame($greenMediaId, Uuid::fromBytesToHex($row['media_id']));
    }

    public function testActiveInheritance(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            [
                'id' => $ids->create('parent'),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
            ],
            [
                'id' => $ids->create('red'),
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'name' => 'red',
                'stock' => 10,
            ],
            [
                'id' => $ids->create('green'),
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'name' => 'green',
                'active' => false,
            ],
            [
                'id' => $ids->create('blue'),
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'name' => 'green',
                'active' => true,
            ],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create($blogs, $context);

        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids->getList(['red', 'green', 'blue']));
        $criteria->addFilter(new EqualsFilter('active', true));

        $blogs = $this->repository->searchIds($criteria, $context);

        static::assertTrue($blogs->has($ids->get('red')));
        static::assertTrue($blogs->has($ids->get('blue')));
        static::assertFalse($blogs->has($ids->get('green')));
    }

    public function testGuaranteeConfirmedInheritance(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            [
                'id' => $ids->create('parent'),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
                'guaranteeConfirmed' => true,
            ],
            [
                'id' => $ids->create('red'),
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'name' => 'red',
                'stock' => 10,
            ],
            [
                'id' => $ids->create('green'),
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'name' => 'green',
                'stock' => 10,
                'guaranteeConfirmed' => false,
            ],
        ];

        $this->repository->create($blogs, $this->context);

        $stored = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `guarantee_confirmed` FROM `blog` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids->getList(['red', 'green']))],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertNull($stored[$ids->get('red')]);
        static::assertSame('0', $stored[$ids->get('green')]);

        // The Administration reads variants without inheritance and needs null to show the switch as inherited.
        $rawVariants = $this->repository->search(new Criteria($ids->getList(['red', 'green'])), $this->context);

        $red = $rawVariants->getEntities()->get($ids->get('red'));
        static::assertInstanceOf(BlogEntity::class, $red);
        static::assertNull($red->get('guaranteeConfirmed'));
        static::assertFalse($red->isGuaranteeConfirmed());

        $green = $rawVariants->getEntities()->get($ids->get('green'));
        static::assertInstanceOf(BlogEntity::class, $green);
        static::assertFalse($green->get('guaranteeConfirmed'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $variants = $this->repository->search(new Criteria($ids->getList(['red', 'green'])), $context);

        $red = $variants->getEntities()->get($ids->get('red'));
        static::assertInstanceOf(BlogEntity::class, $red);
        static::assertTrue($red->isGuaranteeConfirmed());

        $green = $variants->getEntities()->get($ids->get('green'));
        static::assertInstanceOf(BlogEntity::class, $green);
        static::assertFalse($green->isGuaranteeConfirmed());
    }

    public function testGuaranteeTermsInheritance(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            [
                'id' => $ids->create('parent'),
                'blogNumber' => Uuid::randomHex(),
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
                'guaranteeTermsMedia' => ['id' => $ids->create('terms'), 'fileName' => 'guarantee-terms'],
                'guaranteeTermsUrl' => 'https://example.com/guarantee-terms',
            ],
            [
                'id' => $ids->create('red'),
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'name' => 'red',
                'stock' => 10,
            ],
            [
                'id' => $ids->create('green'),
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'name' => 'green',
                'stock' => 10,
                'guaranteeTermsUrl' => 'https://example.com/green-guarantee-terms',
            ],
        ];

        $this->repository->create($blogs, $this->context);

        $rawRed = $this->repository->search(new Criteria([$ids->get('red')]), $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $rawRed);
        static::assertNull($rawRed->getGuaranteeTermsMediaId());
        static::assertNull($rawRed->getGuaranteeTermsUrl());

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids->getList(['red', 'green']));
        $criteria->addAssociation('guaranteeTermsMedia');

        $variants = $this->repository->search($criteria, $context)->getEntities();

        $red = $variants->get($ids->get('red'));
        static::assertInstanceOf(BlogEntity::class, $red);
        static::assertSame($ids->get('terms'), $red->getGuaranteeTermsMediaId());
        static::assertSame($ids->get('terms'), $red->getGuaranteeTermsMedia()?->getId());
        static::assertSame('https://example.com/guarantee-terms', $red->getGuaranteeTermsUrl());

        $green = $variants->get($ids->get('green'));
        static::assertInstanceOf(BlogEntity::class, $green);
        static::assertSame($ids->get('terms'), $green->getGuaranteeTermsMedia()?->getId());
        static::assertSame('https://example.com/green-guarantee-terms', $green->getGuaranteeTermsUrl());
    }

    public function testVariantInheritanceWithCategories(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentCategory = Uuid::randomHex();
        $greenCategory = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $parentCategory, 'name' => 'parent'],
                ],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $greenCategory, 'name' => 'green'],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($blogs, $context);

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('categories');
        $blogs = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('categories');
        $parents = $this->repository->search($criteria, $context)->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($blogs->has($redId));
        static::assertTrue($blogs->has($greenId));

        $parent = $parents->get($parentId);
        $green = $blogs->get($greenId);
        $red = $blogs->get($redId);

        $parentCategories = $parent->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $parentCategories);
        static::assertSame([$parentCategory], array_values($parentCategories->getIds()));
        $redCategories = $red->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $redCategories);
        static::assertSame([$parentCategory], array_values($redCategories->getIds()));
        $greenCategories = $green->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $greenCategories);
        static::assertSame([$greenCategory], array_values($greenCategories->getIds()));

        /** @var array{category_tree: string, categories: string} $row */
        $row = $this->connection->fetchAssociative('SELECT category_tree, categories FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($parentId, Uuid::fromBytesToHex($row['categories']));

        /** @var array{category_tree: string, categories: string} $row */
        $row = $this->connection->fetchAssociative('SELECT category_tree, categories FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($parentId, Uuid::fromBytesToHex($row['categories']));

        /** @var array{category_tree: string, categories: string} $row */
        $row = $this->connection->fetchAssociative('SELECT category_tree, categories FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertContains($greenCategory, json_decode($row['category_tree'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($greenId, Uuid::fromBytesToHex($row['categories']));
    }

    public function testVariantInheritanceWithCategoriesAndFilters(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentCategory = Uuid::randomHex();
        $greenCategory = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $parentCategory, 'name' => 'parent'],
                ],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $greenCategory, 'name' => 'green'],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($blogs, $context);

        $criteria = new Criteria([$redId, $greenId]);
        $categoryCriteria = $criteria->getAssociation('categories');
        $categoryCriteria->setLimit(1);
        $blogs = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('categories');
        $parents = $this->repository->search($criteria, $context)->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($blogs->has($redId));
        static::assertTrue($blogs->has($greenId));

        $parent = $parents->get($parentId);
        $green = $blogs->get($greenId);
        $red = $blogs->get($redId);

        $parentCategories = $parent->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $parentCategories);
        static::assertSame([$parentCategory], array_values($parentCategories->getIds()));
        $redCategories = $red->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $redCategories);
        static::assertSame([$parentCategory], array_values($redCategories->getIds()));
        $greenCategories = $green->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $greenCategories);
        static::assertSame([$greenCategory], array_values($greenCategories->getIds()));
    }

    public function testSearchByInheritedName(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $parentName,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [$parentPrice],
            ],

            // price should be inherited
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            // name should be inherited
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.name', $parentName));

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertCount(2, $blogs);
        static::assertTrue($blogs->has($parentId));
        static::assertTrue($blogs->has($greenId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.name', $redName));

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($redId));
    }

    public function testSearchByInheritedPrice(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $manufacturerId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => [$parentPrice],
            ],

            // price should be inherited
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            // name should be inherited
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $this->repository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.price', $parentPrice['gross']));
        $criteria->addFilter(new EqualsFilter('blog.manufacturerId', $manufacturerId));

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertCount(2, $blogs);
        static::assertTrue($blogs->has($parentId));
        static::assertTrue($blogs->has($redId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.price', $greenPrice['gross']));

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($greenId));
    }

    public function testSearchCategoriesWithBlogsUseInheritance(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $categoryId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => [$parentPrice],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],

            // price should be inherited
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            // name should be inherited
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $this->repository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.blogs.price', $greenPrice['gross']));

        $repository = static::getContainer()->get('category.repository');
        $categories = $repository->searchIds($criteria, $context);

        static::assertSame(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('category.blogs.price', $parentPrice['gross']),
            new EqualsFilter('category.blogs.parentId', null),
        ]));

        $repository = static::getContainer()->get('category.repository');
        $categories = $repository->searchIds($criteria, $context);

        static::assertSame(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());
    }

    public function testSearchBlogsOverInheritedCategories(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $redCategories = [
            ['id' => $redId, 'name' => 'Red category'],
        ];

        $parentCategories = [
            ['id' => $parentId, 'name' => 'Parent category'],
        ];

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'Parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'categories' => $parentCategories,
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Red',
                'parentId' => $parentId,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'categories' => $redCategories,
            ],

            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
            ],
        ];

        $this->repository->upsert($blogs, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.blogs.name', 'Parent'));

        $repo = static::getContainer()->get('category.repository');
        $result = $repo->search($criteria, $this->context)->getEntities();
        static::assertCount(1, $result);
        static::assertTrue($result->has($parentId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.blogs.name', 'Red'));
        $result = $repo->search($criteria, $this->context)->getEntities();
        static::assertCount(1, $result);
        static::assertTrue($result->has($redId));
    }

    public function testSearchManufacturersWithBlogsUseInheritance(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $manufacturerId = Uuid::randomHex();
        $manufacturerId2 = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => [$parentPrice],
                'manufacturer' => [
                    'id' => $manufacturerId,
                    'name' => 'test',
                ],
            ],
            // price should be inherited
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
                'manufacturer' => [
                    'id' => $manufacturerId2,
                    'name' => 'test',
                ],
            ],
            // manufacturer should be inherited
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog_manufacturer.blogs.price', $greenPrice['gross']));

        $result = static::getContainer()->get('blog_manufacturer.repository')->searchIds($criteria, $context);

        static::assertSame(1, $result->getTotal());
        static::assertContains($manufacturerId, $result->getIds());
    }

    public function testWriteBlogOverCategories(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $categories = [
            [
                'id' => $categoryId,
                'name' => 'Cat1',
                'blogs' => [
                    [
                        'id' => $blogId,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 10,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'test',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $repository = static::getContainer()->get('category.repository');

        $repository->create($categories, Context::createDefaultContext());

        $blogs = $this->repository->search(new Criteria([$blogId]), Context::createDefaultContext())->getEntities();

        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($blogId));

        $blog = $blogs->get($blogId);
        static::assertIsArray($blog->getCategoryTree());
        static::assertContains($categoryId, $blog->getCategoryTree());
    }

    public function testWriteBlogOverManufacturer(): void
    {
        $blogId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();

        $manufacturers = [
            [
                'id' => $manufacturerId,
                'name' => 'Manufacturer',
                'blogs' => [
                    [
                        'id' => $blogId,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 10,
                        'name' => 'test',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'manufacturerId' => $manufacturerId,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $repository = static::getContainer()->get('blog_manufacturer.repository');

        $repository->create($manufacturers, Context::createDefaultContext());

        $blogs = $this->repository->search(new Criteria([$blogId]), Context::createDefaultContext())->getEntities();

        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has($blogId));
        $blog = $blogs->get($blogId);
        static::assertSame($manufacturerId, $blog->getManufacturerId());
    }

    public function testCreateAndAssignBlogProperty(): void
    {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $blueId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'properties' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => 'color'],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('properties');
        $blog = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->get($id);
        static::assertNotNull($blog);

        $sheet = $blog->getProperties();

        static::assertInstanceOf(PropertyGroupOptionCollection::class, $sheet);
        static::assertCount(2, $sheet);

        static::assertTrue($sheet->has($redId));
        static::assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        static::assertSame('red', $red->getName());
        static::assertSame('blue', $blue->getName());

        static::assertSame($colorId, $red->getGroupId());
        static::assertSame($colorId, $blue->getGroupId());
    }

    public function testCreateAndAssignBlogOption(): void
    {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $blueId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'test',
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'options' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => $colorId],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('options');
        $blog = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->get($id);
        static::assertNotNull($blog);

        $sheet = $blog->getOptions();

        static::assertInstanceOf(PropertyGroupOptionCollection::class, $sheet);
        static::assertCount(2, $sheet);

        static::assertTrue($sheet->has($redId));
        static::assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        static::assertSame('red', $red->getName());
        static::assertSame('blue', $blue->getName());

        static::assertSame($colorId, $red->getGroupId());
        static::assertSame($colorId, $blue->getGroupId());
    }

    public function testCreateAndAssignBlogConfigurator(): void
    {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $blueId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'configuratorSettings' => [
                [
                    'id' => $redId,
                    'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 90, 'linked' => false],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('configuratorSettings.option');
        $blog = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->get($id);
        static::assertNotNull($blog);

        $configuratorSettings = $blog->getConfiguratorSettings();

        static::assertInstanceOf(BlogConfiguratorSettingCollection::class, $configuratorSettings);
        static::assertCount(2, $configuratorSettings);

        static::assertTrue($configuratorSettings->has($redId));
        static::assertTrue($configuratorSettings->has($blueId));

        $blue = $configuratorSettings->get($blueId);
        $red = $configuratorSettings->get($redId);

        static::assertEquals(['net' => 25, 'gross' => 50, 'linked' => false, 'currencyId' => Defaults::CURRENCY], $red->getPrice());
        static::assertEquals(['net' => 90, 'gross' => 100, 'linked' => false, 'currencyId' => Defaults::CURRENCY], $blue->getPrice());

        $redOption = $red->getOption();
        static::assertInstanceOf(PropertyGroupOptionEntity::class, $redOption);
        static::assertSame('red', $redOption->getName());
        $blueOption = $blue->getOption();
        static::assertInstanceOf(PropertyGroupOptionEntity::class, $blueOption);
        static::assertSame('blue', $blueOption->getName());

        static::assertSame($colorId, $redOption->getGroupId());
        static::assertSame($colorId, $blueOption->getGroupId());
    }

    public function testCreateAndAssignCMSPage(): void
    {
        $id = Uuid::randomHex();

        $cmsPageId = Uuid::randomHex();

        static::getContainer()->get('cms_page.repository')->create(
            [
                [
                    'id' => $cmsPageId,
                    'type' => 'blog_detail',
                ],
            ],
            $this->context
        );

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'cmsPageId' => $cmsPageId,
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('cmsPage');
        $blog = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->get($id);
        static::assertNotNull($blog);
        static::assertSame($cmsPageId, $blog->getCmsPageId());
        $cmsPage = $blog->getCmsPage();
        static::assertInstanceOf(CmsPageEntity::class, $cmsPage);
        static::assertSame('blog_detail', $cmsPage->getType());
    }

    public function testModifyBlogPriceMatrix(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $id,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(1, $blog->getPrices());

        $price = $blog->getPrices()->first();
        static::assertNotNull($price);
        static::assertSame($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'prices' => [
                // update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 5000, 'net' => 4000, 'linked' => false],
                    ],
                ],

                // add new graduation to existing rule
                [
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());

        $price = $blog->getPrices()->get($id);
        static::assertNotNull($price);

        static::assertSame($ruleA, $price->getRuleId());
        static::assertInstanceOf(PriceCollection::class, $price->getPrice());

        static::assertEquals(
            new Price(Defaults::CURRENCY, 4000, 5000, false),
            $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY)
        );

        static::assertSame(1, $price->getQuantityStart());
        static::assertSame(20, $price->getQuantityEnd());

        $id3 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'prices' => [
                [
                    'id' => $id3,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blogs = $this->repository->search($criteria, $context)->getEntities();
        static::assertTrue($blogs->has($id));

        $blog = $blogs->get($id);

        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(3, $blog->getPrices());

        $price = $blog->getPrices()->get($id3);
        static::assertNotNull($price);
        static::assertSame($ruleB, $price->getRuleId());

        static::assertEquals(
            new Price(Defaults::CURRENCY, 50, 50, false),
            $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY)
        );

        static::assertSame(1, $price->getQuantityStart());
        static::assertNull($price->getQuantityEnd());
    }

    public function testWriteBlogCategoriesWithoutId(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'name' => 'blog',
            'stock' => 10,
            'ean' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'manufacturer'],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
            'categories' => [
                ['name' => 'category_name'],
            ],
        ];
        $this->connection->executeStatement('DELETE FROM channel');
        $this->connection->executeStatement('DELETE FROM category');

        $this->repository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative('SELECT * FROM category');

        static::assertCount(1, $count, print_r($count, true));
    }

    public function testDuplicateBlogNumber(): void
    {
        $blogNumber = 'sw1' . Uuid::randomHex();

        $data = [
            'id' => Uuid::randomHex(),
            'blogNumber' => $blogNumber,
            'name' => 'blog',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'manufacturer'],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $data = [
            'id' => Uuid::randomHex(),
            'blogNumber' => $blogNumber,
            'name' => 'blog',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'manufacturer'],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
        ];

        $this->expectExceptionObject(new DuplicateBlogNumberException($blogNumber));

        $this->repository->create([$data], Context::createDefaultContext());
    }

    public function testPriceSortingWithDecimalPrecision(): void
    {
        $defaults = [
            'name' => 'blog',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
        ];

        $ids = new IdsCollection();

        $data = [
            array_replace_recursive($defaults, ['id' => $ids->create('a'), 'price' => [['gross' => 99.96]], 'blogNumber' => $ids->get('a')]),
            array_replace_recursive($defaults, ['id' => $ids->create('b'), 'price' => [['gross' => 99.92]], 'blogNumber' => $ids->get('b')]),
            array_replace_recursive($defaults, ['id' => $ids->create('c'), 'price' => [['gross' => 99.95]], 'blogNumber' => $ids->get('c')]),
            array_replace_recursive($defaults, ['id' => $ids->create('d'), 'price' => [['gross' => 99.91]], 'blogNumber' => $ids->get('d')]),
        ];

        $this->repository->create($data, Context::createDefaultContext());

        $criteria = new Criteria($ids->all());
        $criteria->addSorting(new FieldSorting('price'));

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(
            array_values($ids->getList(['d', 'b', 'c', 'a'])),
            $result->getIds()
        );

        $criteria = new Criteria($ids->all());
        $criteria->addSorting(new FieldSorting('price', 'DESC'));

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(
            array_values($ids->getList(['a', 'c', 'b', 'd'])),
            $result->getIds()
        );
    }

    public function testPriceSortingWithDifferentCurrencyNoFallback(): void
    {
        $ids = new IdsCollection();
        $isoCode = 'DEM';
        $currencyFactor = 0.5;
        $ids->create($isoCode);

        static::getContainer()->get('currency.repository')->create(
            [
                [
                    'id' => $ids->get($isoCode),
                    'factor' => $currencyFactor,
                    'shortName' => 'test',
                    'name' => 'name',
                    'symbol' => 'DM',
                    'isoCode' => $isoCode,
                    'decimalPrecision' => 2,
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                ],
            ],
            Context::createDefaultContext()
        );

        $data = [
            (new BlogBuilder($ids, 'a'))->price(99.94, null, 'default', 99.94)->build(),
            (new BlogBuilder($ids, 'b'))->price(15, 10)->price(99.93, null, $isoCode, 99.93)->build(),
            (new BlogBuilder($ids, 'c'))->price(15, 10)->price(99.97, null, $isoCode, 99.97)->build(),
            (new BlogBuilder($ids, 'd'))->price(15, 10)->price(99.91, null, $isoCode, 99.91)->build(),
            (new BlogBuilder($ids, 'e'))->price(15, 10)->price(99.95, null, $isoCode, 99.95)->build(),
        ];

        $this->repository->create($data, Context::createDefaultContext());

        foreach (['', '.listPrice'] as $priceType) {
            $criteria = new Criteria($ids->all());
            $criteria->addSorting(new FieldSorting(\sprintf('price.%s%s.gross', $ids->get($isoCode), $priceType)));

            $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

            static::assertSame(
                array_values($ids->getList(['a', 'd', 'b', 'e', 'c'])),
                $result->getIds()
            );

            $criteria = new Criteria($ids->all());
            $criteria->addSorting(new FieldSorting(\sprintf('price.%s%s.gross', $ids->get($isoCode), $priceType), 'DESC'));

            $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

            static::assertSame(
                array_values($ids->getList(['c', 'e', 'b', 'd', 'a'])),
                $result->getIds()
            );
        }

        // test context with currency id
        $context = new Context(new SystemSource(), [], $ids->get($isoCode), [Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, $currencyFactor);

        $criteria = new Criteria($ids->all());
        $criteria->addSorting(new FieldSorting('price'));

        $result = $this->repository->searchIds($criteria, $context);

        static::assertSame(
            array_values($ids->getList(['a', 'd', 'b', 'e', 'c'])),
            $result->getIds()
        );
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function customFieldVariantsProvider(): iterable
    {
        yield 'Test own values' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                ],
            ],
            [
                'parent' => ['foo' => 'parent'],
                'child' => ['foo' => 'child'],
            ],
            self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test merged with parent' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                ],
            ],
            [
                'parent' => ['foo' => 'parent', 'bar' => 'parent'],
                'child' => ['foo' => 'child', 'bar' => 'parent'],
            ],
            self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test not merged with parent, no inheritance' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                ],
            ],
            [
                'parent' => ['foo' => 'parent', 'bar' => 'parent'],
                'child' => ['foo' => 'child'],
            ],
            self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], false),
        ];
        yield 'Test inheritance child null value' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                ],
            ],
            [
                'parent' => ['foo' => 'parent'],
                'child' => ['foo' => 'parent'],
            ],
            self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test child null value no inheritance' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                ],
            ],
            [
                'parent' => ['foo' => 'parent'],
                'child' => [],
            ],
            self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], false),
        ];
        yield 'Test child and parent null value no inheritance' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                ],
            ],
            [
                'parent' => [],
                'child' => [],
            ],
            self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], false),
        ];
        yield 'Test only parent null value with inheritance' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                ],
            ],
            [
                'parent' => [],
                'child' => ['foo' => 'child'],
            ],
            self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test inheritance with language chain' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated'],
                'child' => ['foo' => 'child translated'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test inheritance with language chain merged with parent' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated'],
                'child' => ['foo' => 'parent translated'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test inheritance with language chain no translation for language' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
            ],
            [
                'parent' => ['foo' => 'parent'],
                'child' => ['foo' => 'child'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test inheritance with language chain no translation for language and child at all' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
            ],
            [
                'parent' => ['foo' => 'parent'],
                'child' => ['foo' => 'parent'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test language chain without inheritance' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
            ],
            [
                'parent' => ['foo' => 'parent'],
                'child' => [],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
        ];
        yield 'Test language chain without inheritance but language is set' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated'],
                'child' => [],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
        ];
        yield 'Test language chain without inheritance but language is set, main is not' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated'],
                'child' => [],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
        ];
        yield 'Test language chain without inheritance and only main language set' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                ],
            ],
            [
                'parent' => ['foo' => 'parent'],
                'child' => ['foo' => 'child'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
        ];
        yield 'Test language with inheritance and merge with parent and languages' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated', 'bar' => 'parent'],
                'child' => ['foo' => 'child translated', 'bar' => 'parent'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test language with inheritance and merge with parent and languages, child own values' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated', 'bar' => 'child translated']],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated', 'bar' => 'parent'],
                'child' => ['foo' => 'child translated', 'bar' => 'child translated'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test language with inheritance and merge with parent and languages, main child has values' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child', 'bar' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated', 'bar' => 'parent'],
                'child' => ['foo' => 'child translated', 'bar' => 'child'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
        yield 'Test language with inheritance and merge with parent and languages, main child has values and parent language has values' => [
            [
                'parent' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated', 'bar' => 'parent translated']],
                ],
                'child' => [
                    Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child', 'bar' => 'child'], 'name' => 'A'],
                    self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                ],
            ],
            [
                'parent' => ['foo' => 'parent translated', 'bar' => 'parent translated'],
                'child' => ['foo' => 'child translated', 'bar' => 'parent translated'],
            ],
            self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
        ];
    }

    /**
     * @param array<string, mixed> $translations
     * @param array<string, mixed> $expected
     */
    #[DataProvider('customFieldVariantsProvider')]
    public function testVariantCustomFieldInheritance(array $translations, array $expected, Context $context): void
    {
        $ids = new IdsCollection();

        $blogs = [
            [
                'id' => $ids->create('parent'),
                'name' => 'Insert',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'stock' => 1,
                'blogNumber' => Uuid::randomHex(),
                'translations' => $translations['parent'],
            ],
            [
                'id' => $ids->create('child'),
                'parentId' => $ids->get('parent'),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false]],
                'stock' => 2,
                'blogNumber' => Uuid::randomHex(),
                'translations' => $translations['child'],
            ],
        ];

        $this->createLanguage(self::TEST_LANGUAGE_ID);

        $this->repository->create($blogs, Context::createDefaultContext());

        $criteria = new Criteria($ids->getList(['parent', 'child']));

        $blogs = $this->repository->search($criteria, $context)->getEntities();

        foreach ($expected as $key => $customFields) {
            $id = $ids->get('parent');
            if ($key === 'child') {
                $id = $ids->get('child');
            }

            static::assertTrue($blogs->has($id));
            $translation = $blogs->get($id)->getTranslation('customFields');
            static::assertEquals($customFields, $translation);
        }
    }

    public function testCreateVariantWithoutTypeDefaultsToPhysical(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $data = [
            'id' => $parentId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'parent',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'children' => [
                ['id' => $childId, 'blogNumber' => Uuid::randomHex(), 'stock' => 10],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $types = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), `type` FROM blog WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$parentId, $childId])],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertSame(BlogDefinition::TYPE_PHYSICAL, $types[$parentId]);
        static::assertSame(BlogDefinition::TYPE_PHYSICAL, $types[$childId]);
    }

    public function testChildren(): void
    {
        $rootId = 'f1d2554b0ce847cd82f3ac9bd1c0dfca';
        $data = [
            'id' => $rootId,
            'name' => 'Variant blog',
            'blogNumber' => 'TEST',
            'price' => [
                [
                    'gross' => 111,
                    'net' => 111,
                    'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    'linked' => false,
                ],
            ],
            'stock' => 1234,
            'tax' => [
                'taxRate' => 0,
                'name' => 'foo',
            ],
            'manufacturer' => [
                'id' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'name' => 'Test variant manufacturer',
            ],
            'manufacturerId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            'properties' => [
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfba',
                    'name' => 'red',
                    'colorHexCode' => '#ff0000',
                    'group' => [
                        'id' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                        'name' => 'color',
                        'displayType' => 'color',
                    ],
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbb',
                    'name' => 'green',
                    'colorHexCode' => '#00ff00',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbc',
                    'name' => 'blue',
                    'colorHexCode' => '#0000ff',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
            ],
            'children' => [
                [
                    'blogNumber' => 'TEST.1',
                    'stock' => 10,
                    'options' => [
                        ['id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfba'],
                    ],
                ],
                [
                    'blogNumber' => 'TEST.2',
                    'stock' => 10,
                    'options' => [
                        ['id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbb'],
                    ],
                ],
                [
                    'blogNumber' => 'TEST.3',
                    'stock' => 10,
                    'options' => [
                        ['id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbc'],
                    ],
                ],
            ],
            'configuratorSettings' => [
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfaa',
                    'optionId' => 'f1d2554b0ce847cd82f3ac9bd1c0dfba',
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfab',
                    'optionId' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbb',
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfac',
                    'optionId' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbc',
                ],
            ],
        ];

        $this->repository->upsert([$data], Context::createDefaultContext());
        $criteria = new Criteria([$rootId]);
        $criteria->addAssociation('configuratorSettings');
        $blog = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($blog);

        static::assertInstanceOf(BlogConfiguratorSettingCollection::class, $blog->getConfiguratorSettings());
        static::assertCount(3, $blog->getConfiguratorSettings());
    }

    public function testUpdateDescriptionToBeNull(): void
    {
        $id = Uuid::randomHex();
        $description = 'My name is Blog Test';

        $data = [
            'id' => $id,
            'name' => 'test',
            'description' => $description,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        $blog = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($blog);

        static::assertSame($description, $blog->getDescription());

        $this->repository->update([
            ['description' => null, 'id' => $id],
        ], $this->context);

        $blog = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($blog);

        static::assertNull($blog->getDescription());
    }

    public function testUpdatePropertyIdsForVariantsWhenUpdateFromParents(): void
    {
        $context = Context::createDefaultContext();

        $blogId = Uuid::randomHex();
        $variantA = Uuid::randomHex();
        $variantB = Uuid::randomHex();
        $propertyIds = [
            'f1d2554b0ce847cd82f3ac9bd1c0dfba',
            'f1d2554b0ce847cd82f3ac9bd1c0dfbb',
            'f1d2554b0ce847cd82f3ac9bd1c0dfbc',
        ];

        $data = [
            'id' => $blogId,
            'name' => 'Master blog',
            'blogNumber' => 'TEST',
            'price' => [
                [
                    'gross' => 111,
                    'net' => 111,
                    'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    'linked' => false,
                ],
            ],
            'stock' => 1234,
            'tax' => [
                'taxRate' => 0,
                'name' => 'foo',
            ],
            'manufacturer' => [
                'id' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'name' => 'Test variant manufacturer',
            ],
            'manufacturerId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            'properties' => [
                [
                    'id' => $propertyIds[0],
                    'name' => 'red',
                    'colorHexCode' => '#ff0000',
                    'group' => [
                        'id' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                        'name' => 'color',
                        'displayType' => 'color',
                    ],
                ],
                [
                    'id' => $propertyIds[1],
                    'name' => 'green',
                    'colorHexCode' => '#00ff00',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
            ],
            'children' => [
                [
                    'blogNumber' => 'TEST.1',
                    'id' => $variantA,
                    'stock' => 10,
                    'options' => [
                        ['id' => $propertyIds[0]],
                    ],
                ],
                [
                    'blogNumber' => 'TEST.2',
                    'id' => $variantB,
                    'stock' => 10,
                    'options' => [
                        ['id' => $propertyIds[1]],
                    ],
                ],
            ],
            'configuratorSettings' => [
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfaa',
                    'optionId' => $propertyIds[0],
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfab',
                    'optionId' => $propertyIds[1],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $variants = $this->repository->search(new Criteria([$variantB, $variantA]), $context)->getEntities();
        $blog = $this->repository->search(new Criteria([$blogId]), $context)->getEntities()->first();

        static::assertCount(2, $variants);
        static::assertInstanceOf(BlogEntity::class, $blog);

        $blogProperties = $blog->getPropertyIds();
        static::assertTrue($variants->has($variantA));
        $variantAProperties = $variants->get($variantA)->getPropertyIds();
        static::assertTrue($variants->has($variantB));
        $variantBProperties = $variants->get($variantB)->getPropertyIds();

        static::assertIsArray($blogProperties);
        sort($blogProperties);
        static::assertIsArray($variantAProperties);
        sort($variantAProperties);
        static::assertIsArray($variantBProperties);
        sort($variantBProperties);

        static::assertSame($blogProperties, $variantAProperties);
        static::assertSame($blogProperties, $variantBProperties);

        $data = [
            'properties' => [
                [
                    'id' => $propertyIds[2],
                    'name' => 'green',
                    'colorHexCode' => '#00ff00',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
            ],
            'id' => $blogId,
        ];

        $this->repository->upsert([$data], $this->context);

        // depending ids (parents and children) will be queued
        $this->runWorker();

        $variants = $this->repository->search(new Criteria([$variantB, $variantA]), $context)->getEntities();
        $blog = $this->repository->search(new Criteria([$blogId]), $context)->getEntities()->first();

        static::assertCount(2, $variants);
        static::assertInstanceOf(BlogEntity::class, $blog);

        $blogProperties = $blog->getPropertyIds();
        static::assertTrue($variants->has($variantA));
        $variantAProperties = $variants->get($variantA)->getPropertyIds();
        static::assertTrue($variants->has($variantB));
        $variantBProperties = $variants->get($variantB)->getPropertyIds();

        static::assertIsArray($blogProperties);
        sort($blogProperties);
        static::assertIsArray($variantAProperties);
        sort($variantAProperties);
        static::assertIsArray($variantBProperties);
        sort($variantBProperties);

        static::assertSame($blogProperties, $variantAProperties);
        static::assertSame($blogProperties, $variantBProperties);
    }

    public function testInheritanceUpdateOnDeleteRelation(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'x1'))->price(100)->category('c1')->build();
        $this->repository->upsert([$blog], Context::createDefaultContext());

        $event = static::getContainer()->get('category.repository')->delete([['id' => $ids->get('c1')]], Context::createDefaultContext());

        $expected = [
            'category.deleted' => [$ids->get('c1')],
            'blog_category.deleted' => [['blogId' => $ids->get('x1'), 'categoryId' => $ids->get('c1')]],
            'blog_category_tree.deleted' => [['blogId' => $ids->get('x1'), 'categoryId' => $ids->get('c1')]],
            'blog.written' => [$ids->get('x1')],
        ];

        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $event->getList());
            static::assertSame($value, $event->getList()[$key]);
        }
    }

    public function testNotFilterMissingVersionIdField(): void
    {
        $this->createLanguage(self::TEST_LANGUAGE_ID);
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'x1'))
            ->stock(10)
            ->name('Test')
            ->price(10, 9)
            ->seoUrl('/test-detail/' . $ids->get('x1'), 'blog/seo-' . $ids->get('x1'))
            ->build();

        $this->repository->upsert([$blog], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
            new PrefixFilter('seoUrls.pathInfo', '/detail/'),
        ]));

        $ids = $this->repository->searchIds($criteria, $this->context);

        static::assertSame(1, $ids->getTotal());
    }

    /**
     * @param non-empty-list<string> $languages
     */
    private static function createLanguageContext(array $languages, bool $inheritance): Context
    {
        return new Context(new SystemSource(), [], Defaults::CURRENCY, $languages, Defaults::LIVE_VERSION, 1.0, $inheritance);
    }

    /**
     * @param array<string> $ruleIds
     */
    private function createContext(array $ruleIds = []): Context
    {
        return new Context(new SystemSource(), $ruleIds);
    }

    private function createLanguage(string $id, ?string $parentId = Defaults::LANGUAGE_SYSTEM): void
    {
        $languageRepository = static::getContainer()->get('language.repository');

        $languageRepository->upsert(
            [
                [
                    'id' => $id,
                    'name' => \sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'parentId' => $parentId,
                    'active' => true,
                    'translationCode' => [
                        'id' => self::TEST_LOCALE_ID,
                        'code' => self::TEST_LANGUAGE_LOCALE_CODE,
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'channels' => [
                        ['id' => TestDefaults::CHANNEL],
                    ],
                    'channelDefaultAssignments' => [
                        ['id' => TestDefaults::CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
