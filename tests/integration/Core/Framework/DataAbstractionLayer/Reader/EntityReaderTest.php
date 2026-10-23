<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Reader;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationEntity;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception\ParentAssociationCanNotBeFetched;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ConsistsOfManyToManyDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NonIdFieldNamePrimaryKeyTestDefinition;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Integration\Builder\Order\OrderBuilder;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class EntityReaderTest extends TestCase
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

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    private string $deLanguageId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->languageRepository = static::getContainer()->get('language.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->deLanguageId = $this->getDeDeLanguageId();

        $this->registerDefinition(NonIdFieldNamePrimaryKeyTestDefinition::class);
        $this->registerDefinition(ConsistsOfManyToManyDefinition::class);

        $this->connection->rollBack();

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `non_id_primary_key_test`;
            CREATE TABLE `non_id_primary_key_test` (
                `test_field` BINARY(16) NOT NULL,
                `non_pk` BINARY(16) NULL,
                `name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`test_field`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            DROP TABLE IF EXISTS `acme_consists_of_mapping`;
            CREATE TABLE acme_consists_of_mapping (
                blog_id         binary(16) not null,
                blog_version_id binary(16) not null,
                blog_id_to      binary(16) not null,
                PRIMARY KEY (blog_id, blog_version_id, blog_id_to)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();

        $this->connection->executeStatement('DROP TABLE `non_id_primary_key_test`');
        $this->connection->executeStatement('DROP TABLE `acme_consists_of_mapping`');
        $this->connection->beginTransaction();

        parent::tearDown();
    }

    public function testPartialLoadingAddsImplicitAssociationToRequestedFields(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->categories(['c1', 'c2'])
            ->visibility()
            ->manufacturer('m1');

        $this->blogRepository
            ->create([$blog->build()], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('p1')]);
        $criteria->addFields(['blogNumber', 'name', 'categories.name']);

        $values = $this->blogRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        $entity = $values->first();

        static::assertInstanceOf(PartialEntity::class, $entity);
        static::assertSame('p1', $entity->get('blogNumber'));
        static::assertSame('p1', $entity->get('name'));
        static::assertNull($entity->get('active'));

        $collection = $entity->get('categories');
        static::assertInstanceOf(EntityCollection::class, $collection);

        static::assertInstanceOf(PartialEntity::class, $collection->first());
        $collection->sortByIdArray([$ids->get('c1'), $ids->get('c2')]);

        static::assertSame('c1', $collection->first()?->get('name'));
    }

    public function testPartialLoadingManyToOne(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->categories(['c1', 'c2'])
            ->visibility()
            ->manufacturer('m1');

        $this->blogRepository
            ->create([$blog->build()], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('p1')]);
        $criteria->addFields(['id', 'blogNumber', 'name', 'manufacturer.id', 'manufacturer.name']);

        $values = $this->blogRepository->search($criteria, Context::createDefaultContext());

        $entity = $values->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $entity);
        static::assertSame('p1', $entity->get('blogNumber'));
        static::assertSame('p1', $entity->get('name'));
        static::assertNull($entity->get('active'));

        static::assertInstanceOf(PartialEntity::class, $entity->get('manufacturer'));
        static::assertSame($ids->get('m1'), $entity->get('manufacturer')->get('id'));
        static::assertSame('m1', $entity->get('manufacturer')->get('name'));
    }

    public function testPartialLoadingOneToOneWithReferenceFieldInOtherTable(): void
    {
        $ids = new IdsCollection();

        $user = (new UserBuilder($ids, 'user1'));
        $order = (new OrderBuilder($ids, 'order1'))->orderUser('First Name Test', 'user1');

        $this->userRepository->create([$user->build()], Context::createDefaultContext());
        $this->orderRepository->create([$order->build()], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('order1')]);
        $criteria->addFields(['id', 'orderNumber', 'orderUser.firstName']);

        $partialOrder = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $partialOrder);
        static::assertSame('order1', $partialOrder->get('orderNumber'));

        static::assertInstanceOf(PartialEntity::class, $partialOrder->get('orderUser'));
        static::assertSame('First Name Test', $partialOrder->get('orderUser')->get('firstName'));
    }

    public function testPartialLoadingOneToMany(): void
    {
        $ids = new IdsCollection();

        $this->categoryRepository->upsert([
            [
                'id' => $ids->get('c1'),
                'name' => 'test',
                'seoUrls' => [
                    [
                        'id' => $ids->get('c1-url'),
                        'routeName' => 'frontend.category.page',
                        'seoPathInfo' => '/test',
                        'pathInfo' => '/test',
                        'languageId' => $this->deLanguageId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('c1')]);
        $criteria->addAssociation('seoUrls');
        $criteria->addFields(['name', 'seoUrls.routeName']);

        $values = $this->categoryRepository
            ->search($criteria, Context::createDefaultContext());

        $entity = $values->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $entity);
        static::assertSame('test', $entity->get('name'));
        static::assertNull($entity->get('type'));
        static::assertInstanceOf(EntityCollection::class, $entity->get('seoUrls'));
        static::assertInstanceOf(PartialEntity::class, $entity->get('seoUrls')->first());
        static::assertSame('frontend.category.page', $entity->get('seoUrls')->first()->get('routeName'));
        static::assertNull($entity->get('seoUrls')->first()->get('pathInfo'));

        // Test same with pagination

        $criteria->setLimit(50);
        $criteria->getAssociation('seoUrls')->setLimit(50);
        $values = $this->categoryRepository
            ->search($criteria, Context::createDefaultContext());

        $entity = $values->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $entity);
        static::assertSame('test', $entity->get('name'));
        static::assertNull($entity->get('type'));
        static::assertInstanceOf(EntityCollection::class, $entity->get('seoUrls'));
        static::assertInstanceOf(PartialEntity::class, $entity->get('seoUrls')->first());
        static::assertSame('frontend.category.page', $entity->get('seoUrls')->first()->get('routeName'));
        static::assertNull($entity->get('seoUrls')->first()->get('pathInfo'));
    }

    public function testPartialLoadingManyToMany(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            (new BlogBuilder($ids, 'p1'))
                ->price(100)
                ->categories(['c1', 'c2'])
                ->visibility()
                ->manufacturer('m1')
            ->build(),
        ];

        $this->blogRepository
            ->create($blogs, Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('p1')]);
        $criteria->addAssociation('categories');
        $criteria->addAssociation('manufacturer');
        $criteria->getAssociation('categories')->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->addFields(['name', 'categories.name', 'manufacturer.name']);

        $values = $this->blogRepository
            ->search($criteria, Context::createDefaultContext());

        $entity = $values->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $entity);
        static::assertSame('p1', $entity->get('name'));
        static::assertNull($entity->get('type'));
        static::assertInstanceOf(EntityCollection::class, $entity->get('categories'));
        static::assertInstanceOf(PartialEntity::class, $entity->get('categories')->first());
        static::assertSame('c1', $entity->get('categories')->first()->get('name'));
        static::assertInstanceOf(PartialEntity::class, $entity->get('manufacturer'));
        static::assertSame('m1', $entity->get('manufacturer')->get('name'));

        // With pagination
        $criteria->setLimit(50);
        $criteria->getAssociation('categories')->setLimit(50);
        $criteria->getAssociation('manufacturer')->setLimit(50);

        $values = $this->blogRepository
            ->search($criteria, Context::createDefaultContext());

        $entity = $values->getEntities()->first();

        static::assertInstanceOf(PartialEntity::class, $entity);
        static::assertSame('p1', $entity->get('name'));
        static::assertNull($entity->get('type'));
        static::assertInstanceOf(EntityCollection::class, $entity->get('categories'));
        static::assertInstanceOf(PartialEntity::class, $entity->get('categories')->first());
        static::assertSame('c1', $entity->get('categories')->first()->get('name'));
        static::assertInstanceOf(PartialEntity::class, $entity->get('manufacturer'));
        static::assertSame('m1', $entity->get('manufacturer')->get('name'));
    }

    public function testPartialLoadingWithLongAssociationChain(): void
    {
        $ids = new IdsCollection();

        $blogNumber = 'p1';
        $blogs = [
            (new BlogBuilder($ids, $blogNumber))
                ->price(100)
                ->categories(['c1', 'c2'])
                ->visibility()
                ->manufacturer('m1')
                ->cover('cover1')
                ->build(),
        ];
        $context = Context::createDefaultContext();
        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFields(['cover.media']);
        $criteria->addFilter(new EqualsFilter('blogNumber', $blogNumber));
        $criteria->addAssociation('cover.media.thumbnails');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(PartialEntity::class, $blog);
        $cover = $blog->get('cover');
        static::assertInstanceOf(PartialEntity::class, $cover);
        $media = $cover->get('media');
        static::assertInstanceOf(PartialEntity::class, $media);
        $thumbnails = $media->get('thumbnails');
        static::assertInstanceOf(EntityCollection::class, $thumbnails);
        static::assertNotInstanceOf(MediaThumbnailCollection::class, $thumbnails);
    }

    public function testTranslated(): void
    {
        $id = Uuid::randomHex();
        $data = [
            ['id' => $id, 'name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create($data, $context);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [
                Defaults::LANGUAGE_SYSTEM,
                $this->deLanguageId,
            ]
        );

        $categories = $this->categoryRepository
            ->search(new Criteria([$id]), $context)
            ->getEntities();

        $category = $categories->first();

        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertSame('test', $category->getName());
        static::assertSame('test', $category->getTranslated()['name']);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [
                $this->deLanguageId,
                Defaults::LANGUAGE_SYSTEM,
            ]
        );

        $categories = $this->categoryRepository
            ->search(new Criteria([$id]), $context)
            ->getEntities();

        $category = $categories->first();

        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertNull($category->getName());
        static::assertSame('test', $category->getTranslated()['name']);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [
                $this->deLanguageId,
            ]
        );

        $categories = $this->categoryRepository
            ->search(new Criteria([$id]), $context)
            ->getEntities();

        $category = $categories->first();

        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertNull($category->getName());
        static::assertNull($category->getTranslated()['name']);
    }

    public function testTranslatedFieldsContainsNoInheritance(): void
    {
        $id = Uuid::randomHex();

        $subLanguageId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->languageRepository->create([
            [
                'id' => $subLanguageId,
                'name' => 'en_sub',
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'active' => true,
            ],
        ], $context);

        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'EN'],
                $this->deLanguageId => ['name' => 'DE'],
                $subLanguageId => ['description' => 'test'],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        $context = new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$subLanguageId, Defaults::LANGUAGE_SYSTEM]
        );

        $blog = $this->blogRepository
            ->search(new Criteria([$id]), $context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertNull($blog->getName());
        static::assertSame('test', $blog->getDescription());
    }

    public function testInheritedTranslationsInViewData(): void
    {
        $id = Uuid::randomHex();

        $subLanguageId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->languageRepository->create([
            [
                'id' => $subLanguageId,
                'name' => 'en_sub',
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'active' => true,
            ],
        ], $context);

        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'EN'],
                $this->deLanguageId => ['name' => 'DE'],
                $subLanguageId => ['description' => 'test'],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        $context = new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$subLanguageId, Defaults::LANGUAGE_SYSTEM]
        );

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('EN', $blog->getTranslated()['name']);
        static::assertSame('test', $blog->getTranslated()['description']);
    }

    public function testParentInheritanceInViewData(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentTax = Uuid::randomHex();
        $greenTax = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50, 'linked' => true]],
                'tax' => ['id' => $parentTax, 'taxRate' => 13, 'name' => 'parent tax'],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => true]],
                'tax' => ['id' => $greenTax, 'taxRate' => 13, 'name' => 'green tax'],
            ],
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $criteria = new Criteria([$parentId, $greenId, $redId]);
        $criteria->addAssociation('tax');
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(false);
        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(TaxEntity::class, $parent->getTax());
        static::assertInstanceOf(Price::class, $parent->getCurrencyPrice(Defaults::CURRENCY));
        static::assertSame(50.0, $parent->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        $red = $blogs->get($redId);

        // check red blog contains full inheritance of parent
        static::assertInstanceOf(BlogEntity::class, $red);

        // has no own tax
        static::assertNull($red->getTax());
        static::assertNull($red->getTaxId());
        static::assertNull($red->getCurrencyPrice(Defaults::CURRENCY));

        $green = $blogs->get($greenId);

        static::assertInstanceOf(BlogEntity::class, $green);
        static::assertInstanceOf(TaxEntity::class, $green->getTax());
        static::assertSame($greenTax, $green->getTaxId());
        static::assertInstanceOf(Price::class, $green->getCurrencyPrice(Defaults::CURRENCY));
        static::assertSame(100.0, $green->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        $criteria = new Criteria([$parentId, $greenId, $redId]);
        $criteria->addAssociation('tax');
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(TaxEntity::class, $parent->getTax());
        static::assertInstanceOf(Price::class, $parent->getCurrencyPrice(Defaults::CURRENCY));
        static::assertSame(50.0, $parent->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        $red = $blogs->get($redId);

        // check red blog contains full inheritance of parent
        static::assertInstanceOf(BlogEntity::class, $red);

        // price and tax are inherited by parent
        static::assertInstanceOf(TaxEntity::class, $red->getTax());
        static::assertSame($parentTax, $red->getTaxId());
        static::assertInstanceOf(Price::class, $red->getCurrencyPrice(Defaults::CURRENCY));
        static::assertSame(50.0, $red->getCurrencyPrice(Defaults::CURRENCY)->getGross());

        $green = $blogs->get($greenId);
        static::assertInstanceOf(BlogEntity::class, $green);
        static::assertInstanceOf(TaxEntity::class, $green->getTax());
        static::assertSame($greenTax, $green->getTaxId());
        static::assertInstanceOf(Price::class, $green->getCurrencyPrice(Defaults::CURRENCY));
        static::assertSame(100.0, $green->getCurrencyPrice(Defaults::CURRENCY)->getGross());
    }

    public function testInheritanceWithOneToMany(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'quantityEnd' => 20,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                    ],
                    [
                        'quantityStart' => 21,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('prices');
        $context->setConsiderInheritance(true);

        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(BlogPriceCollection::class, $parent->getPrices());

        $red = $blogs->get($redId);
        static::assertInstanceOf(BlogEntity::class, $red);
        $blogPriceCollection = $red->getPrices();
        static::assertNotNull($blogPriceCollection);

        static::assertCount(2, $blogPriceCollection);

        $green = $blogs->get($greenId);
        static::assertInstanceOf(BlogEntity::class, $green);
        static::assertInstanceOf(BlogPriceCollection::class, $green->getPrices());
    }

    public function testInheritanceWithPaginatedOneToMany(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'quantityEnd' => 20,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                    ],
                    [
                        'quantityStart' => 21,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->getAssociation('prices')->setLimit(5);
        $context->setConsiderInheritance(true);

        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(BlogPriceCollection::class, $parent->getPrices());

        $red = $blogs->get($redId);
        static::assertInstanceOf(BlogEntity::class, $red);
        $blogPriceCollection = $red->getPrices();
        static::assertNotNull($blogPriceCollection);
        static::assertCount(2, $blogPriceCollection);

        $green = $blogs->get($greenId);
        static::assertInstanceOf(BlogEntity::class, $green);
        static::assertInstanceOf(BlogPriceCollection::class, $green->getPrices());
    }

    public function testInheritanceWithManyToMany(): void
    {
        $category1 = Uuid::randomHex();
        $category2 = Uuid::randomHex();
        $category3 = Uuid::randomHex();

        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat2'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->create($categories, $context);

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'categories' => [
                    ['id' => $category1],
                    ['id' => $category3],
                ],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $category2],
                ],
            ],
        ];

        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories');
        $context->setConsiderInheritance(true);

        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        $red = $blogs->get($redId);
        $green = $blogs->get($greenId);

        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(BlogEntity::class, $red);
        static::assertInstanceOf(BlogEntity::class, $green);

        // validate parent view data contains same categories
        $categoryCollectionParent = $parent->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $categoryCollectionParent);
        static::assertCount(2, $categoryCollectionParent);
        static::assertTrue($categoryCollectionParent->has($category1));
        static::assertTrue($categoryCollectionParent->has($category3));

        // validate red view data contains the categories of the parent
        $categoryCollection = $red->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $categoryCollection);
        static::assertCount(2, $categoryCollection);
        static::assertTrue($categoryCollection->has($category1));
        static::assertTrue($categoryCollection->has($category3));

        // validate green view data contains same categories
        $categoryCollectionGreen = $green->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $categoryCollectionGreen);
        static::assertCount(1, $categoryCollectionGreen);
        static::assertTrue($categoryCollectionGreen->has($category2));

        // ####
        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories');
        $context->setConsiderInheritance(false);

        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        $red = $blogs->get($redId);
        $green = $blogs->get($greenId);

        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(BlogEntity::class, $red);
        static::assertInstanceOf(BlogEntity::class, $green);

        // validate parent contains own categories
        static::assertCount(2, $categoryCollectionParent);
        static::assertTrue($categoryCollectionParent->has($category1));
        static::assertTrue($categoryCollectionParent->has($category3));

        // validate red contains no own categories
        $redCategories = $red->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $redCategories);
        static::assertCount(0, $redCategories);

        // validate green contains own categories
        static::assertCount(1, $categoryCollectionGreen);
        static::assertTrue($categoryCollectionGreen->has($category2));
    }

    public function testInheritanceWithPaginatedManyToMany(): void
    {
        $category1 = Uuid::randomHex();
        $category2 = Uuid::randomHex();
        $category3 = Uuid::randomHex();

        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat2'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->create($categories, $context);

        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'parent',
                'categories' => [
                    ['id' => $category1],
                    ['id' => $category3],
                ],
            ],
            [
                'id' => $redId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $category2],
                ],
            ],
        ];

        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories')->setLimit(3);
        $context->setConsiderInheritance(true);
        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        $red = $blogs->get($redId);
        $green = $blogs->get($greenId);

        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(BlogEntity::class, $red);
        static::assertInstanceOf(BlogEntity::class, $green);

        // validate parent view data contains same categories
        $parentCategories = $parent->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $parentCategories);
        static::assertCount(2, $parentCategories);
        static::assertTrue($parentCategories->has($category1));
        static::assertTrue($parentCategories->has($category3));

        // validate red view data contains the categories of the parent
        $redCategories = $red->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $redCategories);
        static::assertCount(2, $redCategories);
        static::assertTrue($redCategories->has($category1));
        static::assertTrue($redCategories->has($category3));

        // validate green view data contains same categories
        static::assertInstanceOf(CategoryCollection::class, $green->getCategories());
        static::assertCount(1, $green->getCategories());
        static::assertTrue($green->getCategories()->has($category2));

        $criteria = new Criteria([$greenId, $parentId, $redId]);
        $criteria->addAssociation('categories')->setLimit(3);
        $context->setConsiderInheritance(false);
        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $parent = $blogs->get($parentId);
        $red = $blogs->get($redId);
        $green = $blogs->get($greenId);

        static::assertInstanceOf(BlogEntity::class, $parent);
        static::assertInstanceOf(BlogEntity::class, $red);
        static::assertInstanceOf(BlogEntity::class, $green);

        // validate parent contains own categories
        $parentCategories = $parent->getCategories();
        static::assertNotNull($parentCategories);
        static::assertCount(2, $parentCategories);
        static::assertTrue($parentCategories->has($category1));
        static::assertTrue($parentCategories->has($category3));

        // validate green contains own categories
        $greenCategories = $green->getCategories();
        static::assertNotNull($greenCategories);
        static::assertCount(1, $greenCategories);
        static::assertTrue($greenCategories->has($category2));

        // validate red contains no own categories
        static::assertNotNull($red->getCategories());
        static::assertCount(0, $red->getCategories());
    }

    public function testLoadOneToManyNotLoadedAutomatically(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $user = [
            'id' => $id,
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => TestDefaults::HASHED_PASSWORD,
            'email' => 'test@test.com' . $id,
            'defaultShippingAddressId' => $defaultAddressId,
            'defaultBillingAddressId' => $defaultAddressId,
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
            'addresses' => [
                array_merge(['id' => $defaultAddressId], $address),
                $address,
                $address,
                $address,
                $address,
            ],
        ];

        $this->userRepository->upsert([$user], $context);

        $criteria = new Criteria([$id]);

        $user = $this->userRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertNull($user->getAddresses());
    }

    public function testLoadOneToMany(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $user = [
            'id' => $id,
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => TestDefaults::HASHED_PASSWORD,
            'email' => 'test@test.com' . $id,
            'defaultShippingAddressId' => $defaultAddressId,
            'defaultBillingAddressId' => $defaultAddressId,
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
            'addresses' => [
                array_merge(['id' => $defaultAddressId], $address),
                $address,
                $address,
                $address,
                $address,
            ],
        ];

        $this->userRepository->upsert([$user], $context);

        $addresses = (int) $this->connection->fetchOne('SELECT COUNT(id) FROM user_address WHERE user_id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertSame(5, $addresses);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('addresses');
        $user = $this->userRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertInstanceOf(UserAddressCollection::class, $user->getAddresses());
        static::assertCount(5, $user->getAddresses());
    }

    public function testLoadOneToManySupportsFilter(): void
    {
        $context = Context::createDefaultContext();

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $defaultAddressId1 = Uuid::randomHex();
        $defaultAddressId2 = Uuid::randomHex();

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];
        $user = [
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => TestDefaults::HASHED_PASSWORD,
            'email' => 'test@example.com',
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
        ];

        $this->userRepository->upsert([
            array_merge(
                $user,
                [
                    'id' => $id1,
                    'email' => Uuid::randomHex() . '@example.com',
                    'defaultShippingAddressId' => $defaultAddressId1,
                    'defaultBillingAddressId' => $defaultAddressId1,
                    'addresses' => [
                        array_merge(['id' => $defaultAddressId1], $address),
                        array_merge($address, ['zipcode' => 'B']),
                        array_merge($address, ['zipcode' => 'B']),
                        array_merge($address, ['zipcode' => 'X']),
                    ],
                ]
            ),
            array_merge(
                $user,
                [
                    'id' => $id2,
                    'email' => Uuid::randomHex() . '@example.com',
                    'defaultShippingAddressId' => $defaultAddressId2,
                    'defaultBillingAddressId' => $defaultAddressId2,
                    'addresses' => [
                        array_merge(['id' => $defaultAddressId2], $address),
                        array_merge($address, ['zipcode' => 'B']),
                        array_merge($address, ['zipcode' => 'C']),
                        array_merge($address, ['zipcode' => 'X']),
                    ],
                ]
            ),
        ], $context);

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];

        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM user WHERE id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(2, $mapping);

        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM user_address WHERE user_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(8, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('addresses')
            ->addFilter(new EqualsFilter('user_address.zipcode', 'B'));

        $users = $this->userRepository
            ->search($criteria, $context)
            ->getEntities();

        $user1 = $users->get($id1);
        $user2 = $users->get($id2);

        static::assertInstanceOf(UserEntity::class, $user1);
        static::assertInstanceOf(UserEntity::class, $user2);

        $user1Addresses = $user1->getAddresses();
        static::assertInstanceOf(UserAddressCollection::class, $user1Addresses);
        static::assertCount(2, $user1Addresses);

        $user2Addresses = $user2->getAddresses();
        static::assertInstanceOf(UserAddressCollection::class, $user2Addresses);
        static::assertCount(1, $user2Addresses);
    }

    public function testLoadOneToManySupportsSorting(): void
    {
        $context = Context::createDefaultContext();

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $addressId1 = Uuid::randomHex();
        $addressId2 = Uuid::randomHex();
        $addressId3 = Uuid::randomHex();
        $addressId4 = Uuid::randomHex();
        $addressId5 = Uuid::randomHex();
        $addressId6 = Uuid::randomHex();

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];
        $user = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'password' => TestDefaults::HASHED_PASSWORD,
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
        ];

        $this->userRepository->upsert([
            array_merge(
                $user,
                [
                    'id' => $id1,
                    'email' => 'test@test.com' . Uuid::randomHex(),
                    'defaultShippingAddressId' => $addressId1,
                    'defaultBillingAddressId' => $addressId1,
                    'addresses' => [
                        array_merge($address, ['id' => $addressId1, 'zipcode' => 'C']),
                        array_merge($address, ['id' => $addressId2, 'zipcode' => 'B']),
                        array_merge($address, ['id' => $addressId3, 'zipcode' => 'X']),
                    ],
                ]
            ),
            array_merge(
                $user,
                [
                    'id' => $id2,
                    'email' => 'test@test.com' . Uuid::randomHex(),
                    'defaultShippingAddressId' => $addressId4,
                    'defaultBillingAddressId' => $addressId4,
                    'addresses' => [
                        array_merge($address, ['id' => $addressId4, 'zipcode' => 'X']),
                        array_merge($address, ['id' => $addressId5, 'zipcode' => 'B']),
                        array_merge($address, ['id' => $addressId6, 'zipcode' => 'A']),
                    ],
                ]
            ),
        ], $context);

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];

        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM user WHERE id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(2, $mapping);

        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM user_address WHERE user_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(6, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('addresses')
            ->addSorting(new FieldSorting('user_address.zipcode', FieldSorting::ASCENDING));

        $users = $this->userRepository
            ->search($criteria, $context)
            ->getEntities();

        $user1 = $users->get($id1);
        $user2 = $users->get($id2);

        static::assertInstanceOf(UserEntity::class, $user1);
        static::assertInstanceOf(UserEntity::class, $user2);

        static::assertInstanceOf(UserAddressCollection::class, $user1->getAddresses());
        static::assertCount(3, $user1->getAddresses());
        static::assertSame(
            [$addressId2, $addressId1, $addressId3],
            array_values($user1->getAddresses()->getIds())
        );

        $userAddressCollection = $user2->getAddresses();
        static::assertNotNull($userAddressCollection);
        static::assertCount(3, $userAddressCollection);
        static::assertSame(
            [$addressId6, $addressId5, $addressId4],
            array_values($userAddressCollection->getIds())
        );

        $criteria = new Criteria([$id1, $id2]);
        $criteria->getAssociation('addresses')
            ->addSorting(new FieldSorting('user_address.zipcode', FieldSorting::DESCENDING));

        $users = $this->userRepository
            ->search($criteria, $context)
            ->getEntities();

        $user1 = $users->get($id1);
        $user2 = $users->get($id2);

        static::assertInstanceOf(UserEntity::class, $user1);
        static::assertInstanceOf(UserEntity::class, $user2);

        $user1Addresses = $user1->getAddresses();
        static::assertNotNull($user1Addresses);
        static::assertSame(
            [$addressId3, $addressId1, $addressId2],
            array_values($user1Addresses->getIds())
        );

        $user2Addresses = $user2->getAddresses();
        static::assertNotNull($user2Addresses);
        static::assertSame(
            [$addressId4, $addressId5, $addressId6],
            array_values($user2Addresses->getIds())
        );
    }

    public function testLoadOneToManySupportsSortingAndPagination(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $user = [
            'id' => $id,
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => TestDefaults::HASHED_PASSWORD,
            'email' => 'test@test.com' . Uuid::randomHex(),
            'defaultShippingAddressId' => $defaultAddressId,
            'defaultBillingAddressId' => $defaultAddressId,
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
            'addresses' => [
                array_merge(['id' => $defaultAddressId], $address),
                array_merge($address, ['street' => 'B']),
                array_merge($address, ['street' => 'X']),
                array_merge($address, ['street' => 'E']),
                array_merge($address, ['street' => 'D']),
            ],
        ];

        $this->userRepository->upsert([$user], $context);

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(3);
        $criteria->getAssociation('addresses')->addSorting(new FieldSorting('street'));

        $user = $this->userRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertNotNull($user->getAddresses());
        static::assertCount(3, $user->getAddresses());

        $streets = $user->getAddresses()->map(static fn (UserAddressEntity $e) => $e->getStreet());
        static::assertSame(['A', 'B', 'D'], array_values($streets));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(3);
        $criteria->getAssociation('addresses')->addSorting(new FieldSorting('street', FieldSorting::DESCENDING));

        $user = $this->userRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertNotNull($user->getAddresses());
        static::assertCount(3, $user->getAddresses());

        $streets = $user->getAddresses()->map(static fn (UserAddressEntity $e) => $e->getStreet());
        static::assertSame(['X', 'E', 'D'], array_values($streets));
    }

    public function testLoadOneToManyWithPaginationSupportsSearchTerm(): void
    {
        $context = Context::createDefaultContext();
        $userId = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $this->userRepository->upsert([[
            'id' => $userId,
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => TestDefaults::HASHED_PASSWORD,
            'email' => 'test@test.com' . Uuid::randomHex(),
            'defaultShippingAddressId' => $defaultAddressId,
            'defaultBillingAddressId' => $defaultAddressId,
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
            'addresses' => [
                [
                    'id' => $defaultAddressId,
                    'street' => 'Red',
                    'zipcode' => 'A',
                    'city' => 'A',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'A',
                    'lastName' => 'a',
                    'countryId' => $this->getValidCountryId(),
                ],
                [
                    'street' => 'Dark red',
                    'zipcode' => 'A',
                    'city' => 'A',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'A',
                    'lastName' => 'a',
                    'countryId' => $this->getValidCountryId(),
                ],
                [
                    'street' => 'Blue',
                    'zipcode' => 'A',
                    'city' => 'A',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'A',
                    'lastName' => 'a',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
        ]], $context);

        $criteria = new Criteria([$userId]);
        $criteria->getAssociation('addresses')->setLimit(5)->setTerm('red');

        $user = $this->userRepository->search($criteria, $context)->getEntities()->get($userId);
        static::assertInstanceOf(UserEntity::class, $user);

        $addresses = $user->getAddresses();
        static::assertInstanceOf(UserAddressCollection::class, $addresses);

        $streets = $addresses->map(static fn (UserAddressEntity $address) => $address->getStreet());
        sort($streets);

        static::assertSame(['Dark red', 'Red'], $streets);
    }

    public function testLoadOneToManyWithPaginationIgnoresScoreSortingWithoutScoreQuery(): void
    {
        $context = Context::createDefaultContext();
        $userId = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $this->userRepository->upsert([[
            'id' => $userId,
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => TestDefaults::HASHED_PASSWORD,
            'email' => 'test@test.com' . Uuid::randomHex(),
            'defaultShippingAddressId' => $defaultAddressId,
            'defaultBillingAddressId' => $defaultAddressId,
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
            'addresses' => [
                [
                    'id' => $defaultAddressId,
                    'street' => 'A',
                    'zipcode' => 'A',
                    'city' => 'A',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'A',
                    'lastName' => 'a',
                    'countryId' => $this->getValidCountryId(),
                ],
                [
                    'street' => 'B',
                    'zipcode' => 'A',
                    'city' => 'A',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'A',
                    'lastName' => 'a',
                    'countryId' => $this->getValidCountryId(),
                ],
                [
                    'street' => 'C',
                    'zipcode' => 'A',
                    'city' => 'A',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'A',
                    'lastName' => 'a',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
        ]], $context);

        $criteria = new Criteria([$userId]);
        $criteria->getAssociation('addresses')
            ->setLimit(3)
            ->addSorting(new FieldSorting(Criteria::SCORE_FIELD));

        $user = $this->userRepository->search($criteria, $context)->getEntities()->get($userId);
        static::assertInstanceOf(UserEntity::class, $user);

        $addresses = $user->getAddresses();
        static::assertInstanceOf(UserAddressCollection::class, $addresses);
        static::assertCount(3, $addresses);
    }

    public function testLoadOneToManySupportsPagination(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();

        $address = [
            'street' => 'A',
            'zipcode' => 'A',
            'city' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'A',
            'lastName' => 'a',
            'countryId' => $this->getValidCountryId(),
        ];

        $user = [
            'id' => $id,
            'firstName' => 'Test',
            'lastName' => 'Test',
            'userNumber' => 'A',
            'salutationId' => $this->getValidSalutationId(),
            'password' => TestDefaults::HASHED_PASSWORD,
            'email' => 'test@test.com' . Uuid::randomHex(),
            'defaultShippingAddressId' => $defaultAddressId,
            'defaultBillingAddressId' => $defaultAddressId,
            'channelId' => TestDefaults::CHANNEL,
            'group' => ['name' => 'test'],
            'addresses' => [
                array_merge(['id' => $defaultAddressId], $address),
                $address,
                $address,
                $address,
                $address,
            ],
        ];

        $this->userRepository->upsert([$user], $context);

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(1);

        $user = $this->userRepository->search($criteria, $context)
            ->getEntities()
            ->get($id);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertNotNull($user->getAddresses());
        static::assertCount(1, $user->getAddresses());

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('addresses')->setLimit(3);
        $user = $this->userRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertNotNull($user->getAddresses());
        static::assertCount(3, $user->getAddresses());
    }

    public function testLoadManyToManyNotLoadedAutomatically(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $blog1 = [
            'id' => $id1,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog2 = [
            'id' => $id2,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog3 = [
            'id' => $id3,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $repository = $this->categoryRepository;
        $context = Context::createDefaultContext();

        $repository->upsert(
            [
                ['id' => $id1, 'stock' => 1, 'name' => 'test', 'blogs' => [$blog1, $blog3]],
                ['id' => $id2, 'stock' => 1, 'name' => 'test', 'blogs' => [$blog3, $blog2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM blog_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(4, $mapping);

        // test many to many not loaded automatically
        $categories = $repository
            ->search(new Criteria([$id1, $id2]), $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertNull($category1->getBlogs());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertNull($category2->getBlogs());
    }

    public function testLoadNestedAssociation(): void
    {
        $manufacturerId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $manufacturer = [
            'id' => $manufacturerId,
            'name' => 'Test manufacturer',
            'blogs' => [
                [
                    'id' => $blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'test media',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'active' => true,
                    'tax' => ['taxRate' => 13, 'name' => 'green'],
                    'categories' => [
                        [
                            'id' => $categoryId,
                            'name' => 'foobar',
                        ],
                    ],
                ],
            ],
        ];

        $manufacturerRepo = static::getContainer()->get('blog_manufacturer.repository');
        $context = Context::createDefaultContext();
        $manufacturerRepo->upsert([$manufacturer], $context);

        $manufacturerCriteria = new Criteria([$manufacturerId]);

        $manufacturerCriteria->getAssociation('blogs')
            ->setIds([$blogId])
            ->addAssociation('categories');

        $manufacturer = $manufacturerRepo->search($manufacturerCriteria, $context)->getEntities()->get($manufacturerId);
        static::assertInstanceOf(BlogManufacturerEntity::class, $manufacturer);
        $blogs = $manufacturer->getBlogs();
        static::assertNotNull($blogs);

        static::assertCount(1, $blogs);
        static::assertInstanceOf(BlogEntity::class, $blogs->first());

        $categories = $blogs->first()->getCategories();
        static::assertNotNull($categories);
        static::assertCount(1, $categories);
        static::assertInstanceOf(CategoryEntity::class, $categories->first());
    }

    public function testLoadManyToMany(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $blog1 = [
            'id' => $id1,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog2 = [
            'id' => $id2,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog3 = [
            'id' => $id3,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert(
            [
                ['id' => $id1, 'name' => 'test', 'blogs' => [$blog1, $blog3]],
                ['id' => $id2, 'name' => 'test', 'blogs' => [$blog3, $blog2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM blog_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(4, $mapping);

        // test that we can add the association and all blogs are fetched
        $criteria = new Criteria([$id1, $id2]);

        $criteria->addAssociation('blogs');
        $categories = $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(BlogCollection::class, $category1->getBlogs());
        static::assertCount(2, $category1->getBlogs());

        static::assertContains($id1, $category1->getBlogs()->getIds());
        static::assertContains($id3, $category1->getBlogs()->getIds());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(BlogCollection::class, $category2->getBlogs());
        static::assertCount(2, $category2->getBlogs());

        static::assertContains($id2, $category2->getBlogs()->getIds());
        static::assertContains($id3, $category2->getBlogs()->getIds());

        $criteria = new Criteria([$id1, $id2]);
        $criteria->getAssociation('blogs')->setIds([$id1]);

        $categories = $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertSame([$id1], array_values($category1->getBlogs()?->getIds() ?? []));

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertSame([], $category2->getBlogs()?->getIds() ?? []);

        $criteria = new Criteria([$id1, $id2]);
        $criteria->getAssociation('blogs')
            ->setIds([$id1])
            ->addSorting(new FieldSorting('blog.name'));

        $categories = $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertSame([$id1], array_values($category1->getBlogs()?->getIds() ?? []));

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertSame([], $category2->getBlogs()?->getIds() ?? []);
    }

    public function testLoadManyToManySupportsFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $blog1 = [
            'id' => $id1,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog2 = [
            'id' => $id2,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog3 = [
            'id' => $id3,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert(
            [
                ['id' => $id1, 'name' => 'test', 'blogs' => [$blog1, $blog3]],
                ['id' => $id2, 'name' => 'test', 'blogs' => [$blog3, $blog2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM blog_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(4, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('blogs')
            ->addFilter(new EqualsFilter('blog.active', true));

        $categories = $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(BlogCollection::class, $category1->getBlogs());
        static::assertCount(1, $category1->getBlogs());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(BlogCollection::class, $category2->getBlogs());
        static::assertCount(0, $category2->getBlogs());
    }

    public function testLoadManyToManyWithFilteringOutAllAssociatedEntities(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $blog1 = [
            'id' => $id1,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog2 = [
            'id' => $id2,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog3 = [
            'id' => $id3,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert(
            [
                ['id' => $id1, 'name' => 'test', 'blogs' => [$blog1, $blog3]],
                ['id' => $id2, 'name' => 'test', 'blogs' => [$blog3, $blog2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM blog_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(4, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('blogs')
            ->addFilter(new EqualsFilter('blog.name', 'foo'));

        $categories = $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(BlogCollection::class, $category1->getBlogs());
        static::assertCount(0, $category1->getBlogs());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(BlogCollection::class, $category2->getBlogs());
        static::assertCount(0, $category2->getBlogs());
    }

    public function testLoadManyToManyWithoutAnyAssociatedEntities(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $blog1 = [
            'id' => $id1,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog2 = [
            'id' => $id2,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog3 = [
            'id' => $id3,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $context = Context::createDefaultContext();

        $this->blogRepository->upsert([$blog1, $blog2, $blog3], $context);

        $this->categoryRepository->upsert(
            [
                ['id' => $id1, 'name' => 'test'],
                ['id' => $id2, 'name' => 'test'],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM blog_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(0, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('blogs');

        $categories = $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(BlogCollection::class, $category1->getBlogs());
        static::assertCount(0, $category1->getBlogs());

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(BlogCollection::class, $category2->getBlogs());
        static::assertCount(0, $category2->getBlogs());
    }

    public function testLoadManyToManySupportsSorting(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $blog1 = [
            'id' => $id1,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'A',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog2 = [
            'id' => $id2,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'B',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $blog3 = [
            'id' => $id3,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => false,
            'manufacturer' => ['name' => 'test'],
            'name' => 'C',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
        ];

        $context = Context::createDefaultContext();

        $this->categoryRepository->upsert(
            [
                ['id' => $id1, 'name' => 'test', 'blogs' => [$blog1, $blog3]],
                ['id' => $id2, 'name' => 'test', 'blogs' => [$blog3, $blog2]],
            ],
            $context
        );

        $bytes = [Uuid::fromHexToBytes($id1), Uuid::fromHexToBytes($id2)];
        $mapping = $this->connection->fetchAllAssociative('SELECT * FROM blog_category WHERE category_id IN (:ids)', ['ids' => $bytes], ['ids' => ArrayParameterType::BINARY]);
        static::assertCount(4, $mapping);

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('blogs')
            ->addSorting(new FieldSorting('blog.name', FieldSorting::ASCENDING));

        $categories = $this->categoryRepository->search($criteria, $context);

        $category1 = $categories->getEntities()->get($id1);
        $category2 = $categories->getEntities()->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(BlogCollection::class, $category1->getBlogs());
        static::assertCount(2, $category1->getBlogs());

        static::assertSame(
            [$id1, $id3],
            array_values($category1->getBlogs()->getIds())
        );

        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(BlogCollection::class, $category2->getBlogs());
        static::assertCount(2, $category2->getBlogs());

        static::assertSame(
            [$id2, $id3],
            array_values($category2->getBlogs()->getIds())
        );

        $criteria = new Criteria([$id1, $id2]);

        $criteria->getAssociation('blogs')
            ->addSorting(new FieldSorting('blog.name', FieldSorting::DESCENDING));
        $categories = $this->categoryRepository
            ->search($criteria, $context)
            ->getEntities();

        $category1 = $categories->get($id1);
        $category2 = $categories->get($id2);

        static::assertInstanceOf(CategoryEntity::class, $category1);
        $category1Blogs = $category1->getBlogs();
        static::assertNotNull($category1Blogs);
        static::assertSame(
            [$id3, $id1],
            array_values($category1Blogs->getIds())
        );

        static::assertInstanceOf(CategoryEntity::class, $category2);
        $category2Blogs = $category2->getBlogs();
        static::assertNotNull($category2Blogs);
        static::assertSame(
            [$id3, $id2],
            array_values($category2Blogs->getIds())
        );
    }

    public function testLoadManyToManySupportsPagination(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $blogs = [
            [
                'id' => $id1,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => true,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
                'categories' => [
                    ['name' => 'test1'],
                    ['name' => 'test2'],
                    ['name' => 'test3'],
                    ['name' => 'test4'],
                    ['name' => 'test5'],
                    ['name' => 'test6'],
                    ['name' => 'test7'],
                    ['name' => 'test8'],
                    ['name' => 'test9'],
                ],
            ],
            [
                'id' => $id2,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => false,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
                'categories' => [
                    ['name' => 'test10'],
                    ['name' => 'test11'],
                    ['name' => 'test12'],
                    ['name' => 'test13'],
                    ['name' => 'test14'],
                    ['name' => 'test15'],
                    ['name' => 'test16'],
                    ['name' => 'test17'],
                    ['name' => 'test18'],
                ],
            ],
        ];

        $this->blogRepository->upsert($blogs, $context);

        $criteria = new Criteria([$id1, $id2]);
        $criteria->getAssociation('categories')->setLimit(3);

        $blogs = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        static::assertCount(2, $blogs);

        $blog1 = $blogs->get($id1);
        $blog2 = $blogs->get($id2);

        static::assertInstanceOf(BlogEntity::class, $blog1);
        static::assertInstanceOf(BlogEntity::class, $blog2);
        static::assertNotNull($blog1->getCategories());
        static::assertNotNull($blog2->getCategories());

        static::assertCount(3, $blog1->getCategories());
        static::assertCount(3, $blog2->getCategories());
    }

    public function testReadSupportsConditions(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $blogs = [
            [
                'id' => $id1,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => true,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
            [
                'id' => $id2,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'active' => false,
                'manufacturer' => ['name' => 'test'],
                'name' => 'test',
                'tax' => ['taxRate' => 13, 'name' => 'green'],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->blogRepository->upsert($blogs, $context);

        $criteria = new Criteria([$id1, $id2]);

        $blogs = $this->blogRepository->search($criteria, $context);
        static::assertCount(2, $blogs->getEntities());

        $criteria->addFilter(new EqualsFilter('blog.active', true));
        $blogs = $this->blogRepository->search($criteria, $context);
        static::assertCount(1, $blogs->getEntities());
    }

    public function testReadRelationWithNestedToManyRelations(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $data = [
            'id' => Uuid::randomHex(),
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'active' => true,
            'manufacturer' => ['name' => 'test'],
            'name' => 'test',
            'tax' => ['taxRate' => 13, 'name' => 'green'],
            'cover' => [
                'position' => 1,
                'media' => [
                    'id' => $ids->get('media'),
                    'name' => 'test-image',
                    'thumbnails' => [
                        ['id' => Uuid::randomHex(), 'mediaId' => $ids->get('media'), 'width' => 10, 'height' => 10, 'highDpi' => true, 'mediaThumbnailSize' => ['width' => 10, 'height' => 10]],
                        ['id' => Uuid::randomHex(), 'mediaId' => $ids->get('media'), 'width' => 20, 'height' => 20, 'highDpi' => true, 'mediaThumbnailSize' => ['width' => 20, 'height' => 20]],
                        ['id' => Uuid::randomHex(), 'mediaId' => $ids->get('media'), 'width' => 30, 'height' => 30, 'highDpi' => true, 'mediaThumbnailSize' => ['width' => 30, 'height' => 30]],
                    ],
                ],
            ],
        ];

        $this->blogRepository->create([$data], $context);
        $criteria = new Criteria([$data['id']]);
        $criteria->addAssociation('cover.media');
        $results = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities();

        $blog = $results->first();

        static::assertNotNull($blog, 'Blog has not been created.');
        static::assertNotNull($blog->getCover(), 'Cover was not fetched.');
        static::assertNotNull($blog->getCover()->getMedia(), 'Media for cover was not fetched.');
        $mediaThumbnailCollection = $blog->getCover()->getMedia()->getThumbnails();
        static::assertNotNull($mediaThumbnailCollection);
        static::assertCount(3, $mediaThumbnailCollection->getElements(), 'Thumbnails were not fetched or is incomplete.');
    }

    public function testAddTranslationsAssociation(): void
    {
        $id = Uuid::randomHex();

        $cats = [
            [
                'id' => $id,
                'name' => 'system',
                'translations' => [
                    'de-DE' => [
                        'name' => 'deutsch',
                    ],
                ],
            ],
        ];

        $this->categoryRepository->create($cats, Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('translations');

        $cat = $this->categoryRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(CategoryEntity::class, $cat);
        $catTranslations = $cat->getTranslations();
        static::assertNotNull($catTranslations);
        static::assertCount(2, $catTranslations);

        $transDe = $catTranslations->filterByLanguageId($this->deLanguageId)->first();
        static::assertInstanceOf(CategoryTranslationEntity::class, $transDe);
        static::assertSame('deutsch', $transDe->getName());

        $transSystem = $catTranslations->filterByLanguageId(Defaults::LANGUAGE_SYSTEM)->first();
        static::assertInstanceOf(CategoryTranslationEntity::class, $transSystem);
        static::assertSame('system', $transSystem->getName());
    }

    public function testPricesAreConvertedWithCurrencyFactor(): void
    {
        $blogId = Uuid::randomHex();

        $blog = [
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 7, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'test',
            ],
            [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8, 'net' => 6, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'name' => 'test',
            ],
        ];

        $context = Context::createDefaultContext();

        $this->blogRepository->create($blog, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.price', 8.0));

        $searchContext = new Context(
            new SystemSource(),
            $context->getRuleIds(),
            Uuid::randomHex(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            0.8
        );

        $blogs = $this->blogRepository->search($criteria, $searchContext);
        static::assertSame(1, $blogs->getTotal());

        $blog = $blogs->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
    }

    public function testParentCanNotBeFetchedException(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addAssociation('parent');

        $data = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->blogRepository
            ->create([$data], Context::createDefaultContext());

        $exception = null;

        try {
            $this->blogRepository
                ->search($criteria, Context::createDefaultContext());
        } catch (ParentAssociationCanNotBeFetched $e) {
            $exception = $e;
        }

        static::assertInstanceOf(ParentAssociationCanNotBeFetched::class, $exception);
    }

    public function testLoadToOneWithToMany(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            (new BlogBuilder($ids, 'blog-1'))
                ->price(100)
                ->category('test-1')
                ->build(),
            (new BlogBuilder($ids, 'blog-2'))
                ->price(100)
                ->category('test-2')
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('blog-1'), $ids->get('blog-2')]);
        $criteria->addAssociation('translations.language.categoryTranslations');

        $blogs = $this->blogRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        foreach ($blogs as $blog) {
            static::assertInstanceOf(BlogEntity::class, $blog);
            static::assertInstanceOf(EntityCollection::class, $blog->getTranslations());
            static::assertTrue($blog->getTranslations()->count() > 0);

            $first = $blog->getTranslations()->first();

            static::assertInstanceOf(BlogTranslationEntity::class, $first);

            static::assertInstanceOf(LanguageEntity::class, $first->getLanguage());

            static::assertInstanceOf(CategoryTranslationCollection::class, $first->getLanguage()->getCategoryTranslations());

            $translations = $first->getLanguage()->getCategoryTranslations();

            static::assertTrue($translations->count() >= 2);
        }
    }

    public function testSearchWithNonIdPK(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $data = [
            [
                'testField' => $id1,
                'name' => 'test1',
            ],
            [
                'testField' => $id2,
                'nonPk' => null,
                'name' => 'test2',
            ],
            [
                'testField' => $id3,
                'nonPk' => $id3,
                'name' => 'test3',
            ],
        ];

        /** @var EntityRepository<EntityCollection<Entity>> $repository */
        $repository = static::getContainer()->get('non_id_primary_key_test.repository');

        $repository->create($data, Context::createDefaultContext());

        $result = $repository->search(new Criteria(), Context::createDefaultContext());

        static::assertSame(3, $result->getTotal());
        static::assertCount(3, $result->getEntities());

        $foundIds = [];
        foreach ($result->getEntities() as $entity) {
            $foundIds[$entity->getUniqueIdentifier()] = $entity->getUniqueIdentifier();
            if ($entity->getUniqueIdentifier() === $id3) {
                static::assertSame($id3, $entity->get('nonPk'));
            } else {
                static::assertNull($entity->get('nonPk'));
            }
        }

        static::assertEqualsCanonicalizing([$id1, $id2, $id3], array_values($foundIds));
    }

    public function testReadWithNonIdPKOverPropertyName(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            [
                'testField' => $id1,
                'name' => 'test1',
            ],
            [
                'testField' => $id2,
                'name' => 'test2',
            ],
        ];

        /** @var EntityRepository<EntityCollection<Entity>> $repository */
        $repository = static::getContainer()->get('non_id_primary_key_test.repository');

        $repository->create($data, Context::createDefaultContext());

        $result = $repository->search(new Criteria([['testField' => $id1]]), Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertCount(1, $result->getEntities());
    }

    public function testDirectlyReadFromTranslationEntity(): void
    {
        $id = Uuid::randomHex();

        $cats = [
            [
                'id' => $id,
                'name' => 'system',
                'translations' => [
                    'de-DE' => [
                        'name' => 'deutsch',
                    ],
                ],
            ],
        ];

        $this->categoryRepository->create($cats, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'system'));

        $result = static::getContainer()->get('category_translation.repository')->search($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertCount(1, $result->getEntities());

        $translation = $result->getEntities()->first();
        static::assertInstanceOf(CategoryTranslationEntity::class, $translation);
        static::assertSame('system', $translation->getName());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $translation->getLanguageId());
        static::assertSame($id, $translation->getCategoryId());
    }

    /**
     * @param list<array<string, mixed>> $data
     * @param callable(Criteria): void $modifier
     * @param list<string> $expected
     */
    #[DataProvider('casesToManyPaginated')]
    public function testLoadToManyPaginated(array $data, callable $modifier, array $expected): void
    {
        $id = Uuid::randomHex();
        $this->categoryRepository->upsert([
            [
                'id' => $id,
                'name' => 'test',
                'seoUrls' => $data,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $modifier($criteria);

        $result = $this->categoryRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(CategoryEntity::class, $result);
        $seoUrlCollection = $result->getSeoUrls();
        static::assertNotNull($seoUrlCollection);
        $urls = $seoUrlCollection->map(static fn (SeoUrlEntity $e) => $e->getSeoPathInfo());

        static::assertSame($expected, array_values($urls));
    }

    public function testLoadOneToManyPaginatedWithNoParent(): void
    {
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->blogRepository->create([
            [
                'id' => $id = Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
        ], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('media');
        $criteria->getAssociation('media')->setLimit(1);

        $blog = $this->blogRepository
                ->search($criteria, $context)
                ->getEntities()
                ->first();

        static::assertNotNull($blog);
        $blogMediaCollection = $blog->getMedia();
        static::assertNotNull($blogMediaCollection);
        static::assertCount(0, $blogMediaCollection);
    }

    /**
     * @return iterable<string, array{0: list<array<string, mixed>>, 1: callable(Criteria): void, 2: list<string>}>
     */
    public static function casesToManyPaginated(): iterable
    {
        yield 'Multi sort' => [
            [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test2',
                    'pathInfo' => 'test2',
                    'seoPathInfo' => 'active',
                    'isCanonical' => true,
                ],
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test',
                    'pathInfo' => 'test',
                    'seoPathInfo' => 'not-active',
                    'isCanonical' => false,
                ],
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test',
                    'pathInfo' => 'test',
                    'seoPathInfo' => 'not-active2',
                    'isCanonical' => false,
                    'isDeleted' => true,
                ],
            ],
            static function (Criteria $criteria): void {
                $criteria->getAssociation('seoUrls')->addSorting(
                    new FieldSorting('isCanonical', FieldSorting::DESCENDING),
                    new FieldSorting('isDeleted', FieldSorting::ASCENDING),
                )->setLimit(20);
            },
            [
                'active',
                'not-active',
                'not-active2',
            ],
        ];

        yield 'Sorting join new table' => [
            [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test2',
                    'pathInfo' => 'test2',
                    'seoPathInfo' => 'active',
                    'isCanonical' => true,
                ],
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test',
                    'pathInfo' => 'test',
                    'seoPathInfo' => 'not-active',
                    'isCanonical' => false,
                ],
            ],
            static function (Criteria $criteria): void {
                $criteria->getAssociation('seoUrls')->addSorting(
                    new FieldSorting('channel.id', FieldSorting::DESCENDING),
                    new FieldSorting('isCanonical', FieldSorting::DESCENDING)
                )->setLimit(20);
            },
            [
                'active',
                'not-active',
            ],
        ];

        yield 'Sort and boost using query' => [
            [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test2',
                    'pathInfo' => 'test2',
                    'seoPathInfo' => 'active',
                    'isCanonical' => true,
                ],
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test-query',
                    'pathInfo' => 'test-query',
                    'seoPathInfo' => 'active-query',
                    'isCanonical' => false,
                ],
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => 'test',
                    'pathInfo' => 'test',
                    'seoPathInfo' => 'not-active',
                    'isCanonical' => false,
                ],
            ],
            static function (Criteria $criteria): void {
                $filter = new OrFilter([
                    new EqualsFilter('isCanonical', true),
                ]);
                $filter->addQuery(new EqualsFilter('seoPathInfo', 'active-query'));

                $criteria->getAssociation('seoUrls')->setLimit(20);
                $criteria->getAssociation('seoUrls')->addFilter($filter);
                $criteria->getAssociation('seoUrls')->addSorting(new FieldSorting('isCanonical', FieldSorting::ASCENDING));
            },
            [
                'active-query',
                'active',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $criteriaConfig
     * @param list<string> $expectedMedia
     */
    #[DataProvider('casesToManyReadPaginatedInherited')]
    public function testOneToManyReadingInherited(array $criteriaConfig, array $expectedMedia, string $type): void
    {
        $ids = new IdsCollection();

        $variant = new BlogBuilder($ids, 'p1.1');

        $blog = (new BlogBuilder($ids, 'p1'))
            ->name('Test Blog')
            ->price(50, 50);

        if (str_contains($type, 'main')) {
            $blog
                ->media('m-1', 1)
                ->media('m-2', 2)
                ->media('m-3', 3);
        }

        if (str_contains($type, 'variant')) {
            $variant
                ->media('v-1', 1)
                ->media('v-2', 2)
                ->media('v-3', 3);
        }

        $blog->variant($variant->build());

        $context = Context::createDefaultContext();

        $blogRepository = $this->blogRepository;
        $blogRepository->create([
            $blog->build(),
        ], $context);

        $criteria = new Criteria([$ids->get('p1.1')]);
        $media = $criteria->getAssociation('media');
        $media->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $media->assign($criteriaConfig);
        $media->addAssociation('media');

        $context->setConsiderInheritance(true);

        $blog = $blogRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $media = $blog->getMedia();
        static::assertNotNull($media);

        static::assertSame($expectedMedia, array_values($media->map(static function (BlogMediaEntity $m) {
            $mediaEntity = $m->getMedia();
            static::assertNotNull($mediaEntity);

            return $mediaEntity->getFileName();
        })));
    }

    public function testOneToManyPaginationRespectsCrossEntitySort(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->name('Test Blog')
            ->price(50, 50)
            ->media('small', 1)
            ->media('medium', 2)
            ->media('large', 3);

        $this->blogRepository->create([$blog->build()], $context);

        // file_size is WriteProtected, so set it via SQL directly.
        foreach (['small' => 1_000, 'medium' => 5_000, 'large' => 10_000] as $name => $size) {
            $this->connection->executeStatement(
                'UPDATE `media` SET `file_size` = :size WHERE `file_name` = :name',
                ['size' => $size, 'name' => $name]
            );
        }

        // Skip the cover (position 0), sort by descending file size, take only the top 1.
        $criteria = new Criteria([$ids->get('p1')]);
        $criteria->getAssociation('media')
            ->addAssociation('media')
            ->addFilter(new NotEqualsFilter('position', 0))
            ->addSorting(new FieldSorting('media.fileSize', FieldSorting::DESCENDING))
            ->setLimit(1);

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);

        $blogMedia = $blog->getMedia();
        static::assertNotNull($blogMedia);
        static::assertCount(1, $blogMedia);

        $firstBlogMedia = $blogMedia->first();
        static::assertNotNull($firstBlogMedia);
        $firstMedia = $firstBlogMedia->getMedia();
        static::assertNotNull($firstMedia);
        // Must be the image with the highest file_size, not an arbitrary one.
        static::assertSame('large', $firstMedia->getFileName());
    }

    public function testManyToManyJoinsIntoSameTableFiltered(): void
    {
        $field = (new ManyToManyAssociationField(
            'consistsOf',
            BlogDefinition::class,
            ConsistsOfManyToManyDefinition::class,
            'blog_id',
            'blog_id_to'
        ))
            ->addFlags(new ApiAware(), new Extension());

        $field->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));

        static::getContainer()->get(BlogDefinition::class)->getFields()->add($field);

        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->name('Test Blog')
            ->price(50, 50);

        $blog2 = (new BlogBuilder($ids, 'p2'))
            ->name('Test Blog2')
            ->price(50, 50);

        $blog3 = (new BlogBuilder($ids, 'p3'))
            ->name('Test Blog3')
            ->active(false)
            ->price(50, 50);

        $blogRepository = $this->blogRepository;
        $blogRepository->create([
            $blog->build(),
            $blog2->build(),
            $blog3->build(),
        ], Context::createDefaultContext());

        $repository = static::getContainer()->get('acme_consists_of_mapping.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        $repository
            ->create([
                [
                    'blogId' => $ids->get('p1'),
                    'blogIdTo' => $ids->get('p2'),
                ],
                [
                    'blogId' => $ids->get('p1'),
                    'blogIdTo' => $ids->get('p3'),
                ],
            ], Context::createDefaultContext());

        $criteria = new Criteria([$ids->get('p1')]);
        $criteria->addAssociation('consistsOf');

        $criteria->getAssociation('consistsOf')->addFilter(new EqualsFilter('active', true));

        $result = $this->blogRepository
            ->search($criteria, Context::createDefaultContext());

        static::assertCount(1, $result->getEntities());

        $blog = $result->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);

        $consistsOf = $blog->getExtension('consistsOf');

        static::assertInstanceOf(BlogCollection::class, $consistsOf);

        static::assertCount(1, $consistsOf);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: list<string>, 2: string}>
     */
    public static function casesToManyReadPaginatedInherited(): iterable
    {
        yield 'parent-data: with limit at 2 with 3 elements' => [
            ['limit' => 2], // Criteria
            ['m-1', 'm-2'], // expected media
            'main',
        ];

        yield 'parent-data: with limit at 2 and page 2 with 3 elements' => [
            ['limit' => 2, 'offset' => 2],
            ['m-3'],
            'main',
        ];

        yield 'child-data: with limit at 2 with 3 elements' => [
            ['limit' => 2],
            ['v-1', 'v-2'],
            'variant',
        ];

        yield 'child-data: with limit at 2 and page 2 with 3 elements' => [
            ['limit' => 2, 'offset' => 2],
            ['v-3'],
            'variant',
        ];

        yield 'child-and-main: with limit at 2 with 3 elements' => [
            ['limit' => 2],
            ['v-1', 'v-2'],
            'variant-main',
        ];

        yield 'child-and-main: with limit at 2 and page 2 with 3 elements' => [
            ['limit' => 2, 'offset' => 2],
            ['v-3'],
            'variant-main',
        ];
    }
}
