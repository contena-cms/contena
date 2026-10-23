<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Version;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Order\OrderPersister;
use Contena\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Processor;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceEntity;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CalculatedPriceFieldTestDefinition;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\Stub\Rule\TrueRule;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class VersioningTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private Connection $connection;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private AbstractChannelContextFactory $channelContextFactory;

    private Processor $processor;

    private OrderPersisterInterface $orderPersister;

    private Context $context;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->processor = static::getContainer()->get(Processor::class);
        $this->orderPersister = static::getContainer()->get(OrderPersister::class);
        $this->context = Context::createDefaultContext();

        $this->registerDefinition(CalculatedPriceFieldTestDefinition::class);
    }

    public function testChangelogWrittenForRoot(): void
    {
        $id = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => $id,
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'updated', 'taxRate' => 11000],
            'categories' => [
                ['id' => $categoryId, 'name' => 'test'],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->blogRepository->create([$blog], $context);

        $ruleId = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        $price = [
            'id' => $id,
            'blogId' => $id,
            'quantityStart' => 1,
            'ruleId' => $ruleId,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 119,
                    'net' => 100,
                    'linked' => false,
                ],
            ],
        ];

        $priceRepository = static::getContainer()->get('blog_price.repository');

        $event = $priceRepository->create([$price], $context);
        $blogEvent = $event->getEventByEntityName('blog');

        static::assertInstanceOf(EntityWrittenEvent::class, $blogEvent);
        static::assertSame([$id], $blogEvent->getIds());

        $versionId = $this->blogRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);

        $priceRepository->delete([['id' => $id]], $version);

        $commits = $this->getCommits('blog', $id, $versionId);
        static::assertCount(1, $commits);

        $mappingRepository = static::getContainer()->get('blog_category.repository');

        $event = $mappingRepository->delete([['blogId' => $id, 'categoryId' => $categoryId]], $version);

        $blogEvent = $event->getEventByEntityName('blog');
        static::assertInstanceOf(EntityWrittenEvent::class, $blogEvent);

        $categoryEvent = $event->getEventByEntityName('category');
        static::assertInstanceOf(EntityWrittenEvent::class, $categoryEvent);

        // recursion test > order > delivery > position
    }

    public function testChangelogOnlyWrittenForVersionAwareEntities(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'taxRate' => 16,
            'name' => 'test',
        ];

        $context = Context::createDefaultContext();

        static::getContainer()->get('tax.repository')->create([$data], $context);

        $changelog = $this->getVersionData(static::getContainer()->get(TaxDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);

        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'updated', 'taxRate' => 11000],
        ];

        $this->blogRepository->upsert([$blog], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);
        $this->blogRepository->update([['id' => $id, 'name' => 'test']], $version);
        $this->blogRepository->merge($versionId, $context);

        $changelog = $this->getVersionData(static::getContainer()->get(TaxDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);

        $changelog = $this->getVersionData(static::getContainer()->get(BlogDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(1, $changelog);

        $changelog = $this->getVersionData(static::getContainer()->get(BlogManufacturerDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);
    }

    public function testDeleteNoneExistingVersion(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->build();

        $context = Context::createDefaultContext();

        static::getContainer()
            ->get('blog.repository')
            ->create([$blog], $context);

        $versionId = static::getContainer()
            ->get('blog.repository')
            ->createVersion($ids->get('p1'), $context);

        $version = $context->createWithVersionId($versionId);

        static::getContainer()
            ->get('blog.repository')
            ->delete([['id' => $ids->get('p1')]], $version);

        static::getContainer()
            ->get('version.repository')
            ->delete([['id' => $versionId]], $context);

        $e = null;

        try {
            static::getContainer()
                ->get('blog.repository')
                ->merge($versionId, $context);
        } catch (DataAbstractionLayerException $e) {
        }

        static::assertInstanceOf(DataAbstractionLayerException::class, $e);
        static::assertSame(DataAbstractionLayerException::VERSION_NOT_EXISTS, $e->getErrorCode());

        $versions = static::getContainer()
            ->get(Connection::class)
            ->fetchFirstColumn(
                'SELECT LOWER(HEX(version_id)) FROM blog WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('p1'))]
            );

        static::assertContains(Defaults::LIVE_VERSION, $versions);
    }

    public function testICanVersionPriceFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->blogRepository->update([
            [
                'id' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1000, 'net' => 1000, 'linked' => false]],
            ],
        ], $versionContext);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $price = $blog->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(100.0, $price->getGross());
        static::assertSame(10.0, $price->getNet());

        $blog = $this->blogRepository->search(new Criteria([$id]), $versionContext)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $price = $blog->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(1000.0, $price->getGross());
        static::assertSame(1000.0, $price->getNet());

        $this->blogRepository->merge($versionId, $context);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $price = $blog->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(1000.0, $price->getGross());
        static::assertSame(1000.0, $price->getNet());
    }

    public function testICanVersionDateTimeFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
            'releaseDate' => '2018-01-01',
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->blogRepository->update([
            [
                'id' => $id,
                'releaseDate' => '2018-10-05',
            ],
        ], $versionContext);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(\DateTimeInterface::class, $blog->getReleaseDate());
        static::assertSame('2018-01-01', $blog->getReleaseDate()->format('Y-m-d'));

        $blog = $this->blogRepository->search(new Criteria([$id]), $versionContext)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(\DateTimeInterface::class, $blog->getReleaseDate());
        static::assertSame('2018-10-05', $blog->getReleaseDate()->format('Y-m-d'));

        $this->blogRepository->merge($versionId, $context);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(\DateTimeInterface::class, $blog->getReleaseDate());
        static::assertSame('2018-10-05', $blog->getReleaseDate()->format('Y-m-d'));
    }

    public function testICanVersionCalculatedPriceField(): void
    {
        $id = Uuid::randomHex();

        $this->connection->rollBack();

        $this->connection->executeStatement(CalculatedPriceFieldTestDefinition::getCreateTable());

        $this->connection->beginTransaction();

        $price = new CalculatedPrice(
            100.20,
            100.30,
            new CalculatedTaxCollection([
                new CalculatedTax(0.19, 10, 10),
                new CalculatedTax(0.19, 5, 10),
            ]),
            new TaxRuleCollection([
                new TaxRule(10.0, 50),
                new TaxRule(5, 50),
            ])
        );

        $definition = static::getContainer()->get(CalculatedPriceFieldTestDefinition::class);
        static::assertInstanceOf(CalculatedPriceFieldTestDefinition::class, $definition);
        $repository = new EntityRepository(
            $definition,
            static::getContainer()->get(EntityReaderInterface::class),
            static::getContainer()->get(VersionManager::class),
            static::getContainer()->get(EntitySearcherInterface::class),
            static::getContainer()->get(EntityAggregatorInterface::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(EntityLoadedEventFactory::class)
        );

        $context = Context::createDefaultContext();

        $data = ['id' => $id, 'price' => $price];

        $repository->create([$data], $context);

        $versionId = $repository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $newPrice = new CalculatedPrice(
            500.20,
            500.30,
            new CalculatedTaxCollection([
                new CalculatedTax(3.50, 15, 500.20),
                new CalculatedTax(3.50, 30, 500.20),
            ]),
            new TaxRuleCollection([
                new TaxRule(15, 30),
                new TaxRule(30, 70),
            ])
        );

        $updated = ['id' => $id, 'price' => $newPrice];
        $repository->update([$updated], $versionContext);

        $entity = $repository
            ->search(new Criteria([$id]), $context)
            ->getEntities()->first();

        // check that the live entity contains the original price
        static::assertInstanceOf(ArrayEntity::class, $entity);

        $livePrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $livePrice);

        static::assertSame(100.20, $livePrice->getUnitPrice());
        static::assertSame(100.30, $livePrice->getTotalPrice());
        static::assertSame(0.38, $livePrice->getCalculatedTaxes()->getAmount());

        // check that the version entity is updated with the new price
        $entity = $repository
            ->search(new Criteria([$id]), $versionContext)
            ->getEntities()->first();

        static::assertInstanceOf(ArrayEntity::class, $entity);

        $versionPrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $versionPrice);

        static::assertSame(500.20, $versionPrice->getUnitPrice());
        static::assertSame(500.30, $versionPrice->getTotalPrice());
        static::assertSame(7.00, $versionPrice->getCalculatedTaxes()->getAmount());

        $repository->merge($versionId, $context);

        // check that the version entity is updated with the new price
        $entity = $repository
            ->search(new Criteria([$id]), $context)
            ->getEntities()->first();

        static::assertInstanceOf(ArrayEntity::class, $entity);

        $versionPrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $versionPrice);

        static::assertSame(500.20, $versionPrice->getUnitPrice());
        static::assertSame(500.30, $versionPrice->getTotalPrice());
        static::assertSame(7.00, $versionPrice->getCalculatedTaxes()->getAmount());

        $this->connection->rollBack();

        $this->connection->executeStatement(CalculatedPriceFieldTestDefinition::dropTable());
        // We have created a table so the transaction rollback don't work -> we have to do it manually
        $this->connection->executeStatement('DELETE FROM version_commit WHERE version_id = ?', [Uuid::fromHexToBytes($versionId)]);

        $this->connection->beginTransaction();
    }

    public function testICanVersionCalculatedFields(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $data = [
            'id' => $id1,
            'name' => 'category-1',
            'children' => [
                [
                    'id' => $id2,
                    'name' => 'category-2',
                    'children' => [
                        [
                            'id' => $id3,
                            'name' => 'category-3',
                        ],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create([$data], $context);

        $versionId = $this->categoryRepository->createVersion($id3, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $category = $this->categoryRepository->search(new Criteria([$id3]), $versionContext)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|' . $id2 . '|', $category->getPath());

        // update parent of last category in version scope
        $updated = ['id' => $id3, 'parentId' => $id1];

        $this->categoryRepository->update([$updated], $versionContext);

        // check that the path updated
        $category = $this->categoryRepository->search(new Criteria([$id3]), $versionContext)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|', $category->getPath());

        $category = $this->categoryRepository->search(new Criteria([$id3]), $context)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|' . $id2 . '|', $category->getPath());

        $this->categoryRepository->merge($versionId, $context);

        // test after merge the path is updated too
        $category = $this->categoryRepository->search(new Criteria([$id3]), $context)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|', $category->getPath());
    }

    public function testICanVersionTranslatedFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);

        $this->blogRepository->update([['id' => $id, 'name' => 'test']], $version);
        $this->blogRepository->merge($versionId, $context);

        $changelog = $this->getTranslationVersionData(static::getContainer()->get(BlogTranslationDefinition::class)->getEntityName(), Defaults::LANGUAGE_SYSTEM, 'blogId', $id, $context->getVersionId(), 'blogVersionId');

        static::assertCount(1, $changelog);
        static::assertArrayHasKey('name', $changelog[0]['payload']);
        static::assertSame('test', $changelog[0]['payload']['name']);

        $versionId = $this->blogRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);
        $this->blogRepository->update([['id' => $id, 'name' => 'updated']], $version);
        $this->blogRepository->merge($versionId, $context);

        $changelog = $this->getTranslationVersionData(static::getContainer()->get(BlogTranslationDefinition::class)->getEntityName(), Defaults::LANGUAGE_SYSTEM, 'blogId', $id, $context->getVersionId(), 'blogVersionId');

        static::assertCount(2, $changelog);
        static::assertArrayHasKey('name', $changelog[1]['payload']);
        static::assertSame('updated', $changelog[1]['payload']['name']);
    }

    public function testChangelogWrittenForUpdate(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);

        $version = $context->createWithVersionId($versionId);

        $this->blogRepository->upsert([['id' => $id, 'ean' => 'updated']], $version);

        $this->blogRepository->merge($versionId, $context);

        $changelog = $this->getVersionData('blog', $id, $context->getVersionId());

        static::assertCount(1, $changelog);

        // check update written
        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertSame('blog', $changelog[0]['entity_name']);
        static::assertSame('update', $changelog[0]['action']);
    }

    public function testChangelogAppliedAfterMerge(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);

        $changelog = $this->getVersionData('blog', $id, $versionId);

        static::assertCount(1, $changelog);

        // check insert written
        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('blog', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        $versionContext = $context->createWithVersionId($versionId);
        $this->blogRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $changelog = $this->getVersionData('blog', $id, $versionId);

        static::assertCount(2, $changelog);

        // check insert written
        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('blog', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        static::assertSame($id, $changelog[1]['entity_id']['id']);
        static::assertSame($versionId, $changelog[1]['entity_id']['versionId']);
        static::assertSame('blog', $changelog[1]['entity_name']);
        static::assertSame('update', $changelog[1]['action']);

        static::assertArrayHasKey('payload', $changelog[1]);
        static::assertArrayHasKey('ean', $changelog[1]['payload']);
        static::assertSame('updated', $changelog[1]['payload']['ean']);

        $this->blogRepository->merge($versionId, $context);

        $changelog = $this->getVersionData('blog', $id, $context->getVersionId());
        static::assertCount(1, $changelog);

        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertSame('blog', $changelog[0]['entity_name']);
        static::assertSame('update', $changelog[0]['action']);

        static::assertArrayHasKey('payload', $changelog[0]);
        static::assertArrayHasKey('ean', $changelog[0]['payload']);
        static::assertSame('updated', $changelog[0]['payload']['ean']);
    }

    public function testICanVersionOneToManyAssociations(): void
    {
        $blogId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'name' => 'to clone',
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        $versionId = $this->blogRepository->createVersion($blogId, $context);

        // check both blogs exists
        $blogs = $this->connection->fetchAllAssociative('SELECT * FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($blogId)]);
        static::assertCount(2, $blogs);

        $versions = array_map(static fn ($item) => Uuid::fromBytesToHex($item['version_id']), $blogs);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $prices = $this->connection->fetchAllAssociative('SELECT * FROM blog_price WHERE blog_id = :id', ['id' => Uuid::fromHexToBytes($blogId)]);
        static::assertCount(4, $prices);

        $versionPrices = array_filter($prices, static function (array $price) use ($versionId) {
            $version = Uuid::fromBytesToHex($price['version_id']);

            return $version === $versionId;
        });

        static::assertCount(2, $versionPrices);
        foreach ($versionPrices as $price) {
            $blogVersionId = Uuid::fromBytesToHex($price['blog_version_id']);
            static::assertSame($versionId, $blogVersionId);
        }
    }

    public function testICanVersionManyToManyAssociations(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        $versionId = $this->blogRepository->createVersion($blogId, $context);

        // check both blogs exists
        $blogs = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($blogId)]
        );
        static::assertCount(2, $blogs);

        $versions = array_map(static fn ($item) => Uuid::fromBytesToHex($item['version_id']), $blogs);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :id AND blog_version_id = :version',
            ['id' => Uuid::fromHexToBytes($blogId), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :id AND blog_version_id = :version',
            ['id' => Uuid::fromHexToBytes($blogId), 'version' => Uuid::fromHexToBytes($versionId)]
        );
        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }
    }

    public function testICanVersionOrderTags(): void
    {
        $ids = new IdsCollection();
        $orderId = $this->createOrder();

        $this->orderRepository->update([[
            'id' => $orderId,
            'tags' => [
                ['id' => $ids->create('keep'), 'name' => 'keep'],
                ['id' => $ids->create('remove'), 'name' => 'remove'],
            ],
        ]], $this->context);

        $versionId = $this->orderRepository->createVersion($orderId, $this->context);
        $version = $this->context->createWithVersionId($versionId);

        static::assertEqualsCanonicalizing(
            [$ids->get('keep'), $ids->get('remove')],
            $this->getOrderTagIds($orderId, $version)
        );

        static::getContainer()->get('order_tag.repository')->delete([[
            'orderId' => $orderId,
            'orderVersionId' => $versionId,
            'tagId' => $ids->get('remove'),
        ]], $version);

        static::assertSame([$ids->get('keep')], $this->getOrderTagIds($orderId, $version));
        static::assertEqualsCanonicalizing(
            [$ids->get('keep'), $ids->get('remove')],
            $this->getOrderTagIds($orderId, $this->context)
        );
    }

    public function testICanVersionCategoryTags(): void
    {
        $ids = new IdsCollection();

        $this->categoryRepository->create([[
            'id' => $ids->create('category'),
            'name' => 'test',
            'tags' => [['id' => $ids->create('tag'), 'name' => 'test']],
        ]], $this->context);

        $versionId = $this->categoryRepository->createVersion($ids->get('category'), $this->context);

        $tagIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(tag_id)) FROM category_tag WHERE category_id = :id AND category_version_id = :version',
            ['id' => $ids->getBytes('category'), 'version' => Uuid::fromHexToBytes($versionId)]
        );

        static::assertSame([$ids->get('tag')], $tagIds);
    }

    public function testICanReadASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->blogRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $blog = $this->blogRepository->search(new Criteria([$id]), $versionContext)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('updated', $blog->getEan());

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('EAN', $blog->getEan());

        $this->blogRepository->merge($versionId, $context);
        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('updated', $blog->getEan());
    }

    public function testICanMergeIntoNonLiveVersion(): void
    {
        $id = Uuid::randomHex();
        $priceId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
            'prices' => [
                [
                    'id' => $priceId,
                    'quantityStart' => 1,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 40, 'linked' => false]],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        $this->blogRepository->create([$data], $context);

        // Target version is the merge destination.
        $targetVersionId = $this->blogRepository->createVersion($id, $context);
        $targetVersionContext = $context->createWithVersionId($targetVersionId);

        // Source starts from the changed target state.
        $this->blogRepository->update([['id' => $id, 'stock' => 5]], $targetVersionContext);

        $sourceVersionId = $this->blogRepository->createVersion($id, $targetVersionContext);
        $sourceVersionContext = $targetVersionContext->createWithVersionId($sourceVersionId);
        $this->blogRepository->update([[
            'id' => $id,
            'ean' => 'source-version',
            'prices' => [
                [
                    'id' => $priceId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 80, 'net' => 70, 'linked' => false]],
                ],
            ],
        ]], $sourceVersionContext);

        $this->blogRepository->merge($sourceVersionId, $targetVersionContext);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        // Live stays unchanged.
        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('EAN', $blog->getEan());
        static::assertSame(1, $blog->getStock());
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertInstanceOf(BlogPriceEntity::class, $blog->getPrices()->get($priceId));
        $price = $blog->getPrices()->get($priceId)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(50.0, $price->getGross());

        // Target gets source changes and keeps its own stock.
        $blog = $this->blogRepository->search($criteria, $targetVersionContext)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('source-version', $blog->getEan());
        static::assertSame(5, $blog->getStock());
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertInstanceOf(BlogPriceEntity::class, $blog->getPrices()->get($priceId));
        $price = $blog->getPrices()->get($priceId)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(80.0, $price->getGross());

        $changelog = $this->getVersionData('blog', $id, $targetVersionId);
        // Merge changelog belongs to the target version.
        $mergeChangelog = array_values(array_filter(
            $changelog,
            static fn (array $row) => ($row['payload']['ean'] ?? null) === 'source-version'
        ));

        static::assertCount(1, $mergeChangelog);
        static::assertSame($targetVersionId, $mergeChangelog[0]['entity_id']['versionId']);
    }

    public function testICanReadOneToManyInASpecifyVersion(): void
    {
        $blogId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        // create live blog with two prices
        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false],
            ],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false],
                    ],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        // create new version of the blog, blog and prices rows are duplicated now
        $versionId = $this->blogRepository->createVersion($blogId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        // update prices in version scope
        $updated = [
            'id' => $blogId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                ],
            ],
        ];

        $this->blogRepository->update([$updated], $versionContext);

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository->search($criteria, $versionContext)->getEntities()->first();

        // check if the prices are updated in the version scope
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());
        static::assertInstanceOf(BlogPriceEntity::class, $blog->getPrices()->get($priceId1));
        $price1 = $blog->getPrices()->get($priceId1)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price1);
        static::assertSame(100.0, $price1->getGross());
        static::assertSame(100.0, $price1->getNet());
        static::assertInstanceOf(BlogPriceEntity::class, $blog->getPrices()->get($priceId2));
        $price2 = $blog->getPrices()->get($priceId2)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price2);
        static::assertSame(99.0, $price2->getGross());
        static::assertSame(99.0, $price2->getNet());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        // check the prices of the live version are untouched
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());
        static::assertInstanceOf(BlogPriceEntity::class, $blog->getPrices()->get($priceId1));
        $price1 = $blog->getPrices()->get($priceId1)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price1);
        static::assertSame(15.0, $price1->getGross());
        static::assertSame(15.0, $price1->getNet());
        static::assertInstanceOf(BlogPriceEntity::class, $blog->getPrices()->get($priceId2));
        $price2 = $blog->getPrices()->get($priceId2)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price2);
        static::assertSame(10.0, $price2->getGross());
        static::assertSame(10.0, $price2->getNet());

        // now delete the prices in version context
        $priceRepository = static::getContainer()->get('blog_price.repository');
        $priceRepository->delete([
            ['id' => $priceId1, 'versionId' => $versionId],
            ['id' => $priceId2, 'versionId' => $versionId],
        ], $versionContext);

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        // live version scope should be untouched
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());

        // version scope should have no prices
        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository->search($criteria, $versionContext)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(0, $blog->getPrices());

        // now add new prices
        $newPriceId1 = Uuid::randomHex();
        $newPriceId2 = Uuid::randomHex();
        $newPriceId3 = Uuid::randomHex();

        $updated = [
            'id' => $blogId,
            'prices' => [
                [
                    'id' => $newPriceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $newPriceId2,
                    'quantityStart' => 21,
                    'quantityEnd' => 100,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
                [
                    'id' => $newPriceId3,
                    'quantityStart' => 101,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 3, 'linked' => false]],
                ],
            ],
        ];

        // add new price matrix to blog
        $this->blogRepository->update([$updated], $versionContext);

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository->search($criteria, $versionContext)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(3, $blog->getPrices());

        $this->blogRepository->merge($versionId, $context);

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(3, $blog->getPrices());

        $versionId = $this->blogRepository->createVersion($blogId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $newPriceId4 = Uuid::randomHex();

        // check that we can add entities into a sub version using the sub entity repository
        $data = [
            'id' => $newPriceId4,
            'blogId' => $blogId,
            'currencyId' => Defaults::CURRENCY,
            'quantityStart' => 101,
            'ruleId' => $ruleId,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 3, 'linked' => false]],
        ];

        $priceRepository->create([$data], $versionContext);

        $price4 = $priceRepository->search(new Criteria([$newPriceId4]), $versionContext)->getEntities()->first();
        static::assertInstanceOf(BlogPriceEntity::class, $price4);

        static::assertInstanceOf(Price::class, $price4->getPrice()->get(Defaults::CURRENCY));
        static::assertSame(5.0, $price4->getPrice()->get(Defaults::CURRENCY)->getGross());
        static::assertSame($newPriceId4, $price4->getId());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog_price.blogId', $blogId));

        $prices = $priceRepository->search($criteria, $versionContext);
        static::assertCount(4, $prices->getEntities());
        static::assertContains($newPriceId4, $prices->getEntities()->getIds());
    }

    public function testICanReadManyToManyInASpecifyVersion(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        $versionId = $this->blogRepository->createVersion($blogId, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('categories');

        $blog = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(CategoryCollection::class, $blog->getCategories());
        static::assertCount(2, $blog->getCategories());

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :id AND blog_version_id = :version',
            ['id' => Uuid::fromHexToBytes($blogId), 'version' => Uuid::fromHexToBytes($versionId)]
        );

        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }

        $blog = $this->blogRepository
            ->search($criteria, $versionContext)
            ->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(CategoryCollection::class, $blog->getCategories());
        static::assertCount(2, $blog->getCategories());

        $this->blogRepository->merge($versionId, $context);

        $blog = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(CategoryCollection::class, $blog->getCategories());
        static::assertCount(2, $blog->getCategories());
    }

    public function testICanSearchInASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->blogRepository->update([
            [
                'id' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1000, 'net' => 1000, 'linked' => false]],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.price.gross', 1000));

        $blogs = $this->blogRepository->search($criteria, $context);
        static::assertCount(0, $blogs->getEntities());

        $blogs = $this->blogRepository->search($criteria, $versionContext);
        static::assertCount(1, $blogs->getEntities());

        $this->blogRepository->merge($versionId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.price.gross', 1000));

        $blogs = $this->blogRepository->search($criteria, $context);
        static::assertCount(1, $blogs->getEntities());
    }

    public function testICanSearchOneToManyInASpecifyVersion(): void
    {
        $blogId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        // create live blog with two prices
        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
                ],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        // create new version of the blog, blog and prices rows are duplicated now
        $versionId = $this->blogRepository->createVersion($blogId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        // update prices in version scope
        $updated = [
            'id' => $blogId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                ],
            ],
        ];

        $this->blogRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.prices.price.gross', 100));

        // live search shouldn't find anything because the 1000.00 price is only defined in version scope
        $result = $this->blogRepository->searchIds($criteria, $context);
        static::assertCount(0, $result->getIds());

        // version contains should have the price
        $result = $this->blogRepository->searchIds($criteria, $versionContext);
        static::assertCount(1, $result->getIds());
        static::assertContains($blogId, $result->getIds());

        // delete second price to check if the delete is applied too
        static::getContainer()->get('blog_price.repository')->delete([
            ['id' => $priceId2, 'versionId' => $versionId],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.prices.price.gross', 99));
        $result = $this->blogRepository->searchIds($criteria, $versionContext);
        static::assertCount(0, $result->getIds());

        $this->blogRepository->merge($versionId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.prices.price.gross', 100));

        $result = $this->blogRepository->searchIds($criteria, $context);
        static::assertCount(1, $result->getIds());
        static::assertContains($blogId, $result->getIds());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(1, $blog->getPrices());
    }

    public function testICanSearchManyToManyInASpecifyVersion(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $categoryId3 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        $versionId = $this->blogRepository->createVersion($blogId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $updated = [
            'id' => $blogId,
            'categories' => [
                ['id' => $categoryId3, 'name' => 'matching value'],
            ],
        ];
        $this->blogRepository->update([$updated], $versionContext);

        // create criteria which should match only the version scope
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.categories.name', 'matching value'));

        $result = $this->blogRepository->searchIds($criteria, $context);
        static::assertCount(0, $result->getIds());

        $result = $this->blogRepository->searchIds($criteria, $versionContext);
        static::assertCount(1, $result->getIds());
        static::assertContains($blogId, $result->getIds());

        $Criteria = new Criteria([$blogId]);
        $Criteria->addAssociation('categories');

        $blog = $this->blogRepository->search($Criteria, $versionContext)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(CategoryCollection::class, $blog->getCategories());
        static::assertCount(3, $blog->getCategories());

        $this->blogRepository->merge($versionId, $context);

        $result = $this->blogRepository->searchIds($criteria, $context);
        static::assertCount(1, $result->getIds());
        static::assertContains($blogId, $result->getIds());
    }

    public function testSearchConsidersLiveVersionAsFallback(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'id' => $id1,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => null,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create($data, $context);

        $versionId = $this->blogRepository->createVersion($id2, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->blogRepository->update([['id' => $id2, 'ean' => 'EAN']], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.ean', 'EAN'));

        $blogs = $this->blogRepository->search($criteria, $context);
        static::assertCount(1, $blogs->getEntities());

        // in this version we have two blogs with the ean value "EAN"
        $blogs = $this->blogRepository->search($criteria, $versionContext);
        static::assertCount(2, $blogs->getEntities());

        $this->blogRepository->merge($versionId, $context);

        $blogs = $this->blogRepository->search($criteria, $context);
        static::assertCount(2, $blogs->getEntities());
    }

    public function testICanAggregateInASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->blogRepository->update([
            [
                'id' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1000, 'net' => 1000, 'linked' => false]],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('sum_price', 'blog.price.gross'));

        $aggregations = $this->blogRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(100.0, $sum->getSum());

        $aggregations = $this->blogRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());

        $this->blogRepository->merge($versionId, $context);

        $aggregations = $this->blogRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());
    }

    public function testICanAggregateOneToManyInASpecifyVersion(): void
    {
        $blogId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        // create live blog with two prices
        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
                ],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        // create new version of the blog, blog and prices rows are duplicated now
        $versionId = $this->blogRepository->createVersion($blogId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        // update prices in version scope
        $updated = [
            'id' => $blogId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                ],
            ],
        ];

        $this->blogRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.id', $blogId));
        $criteria->addAggregation(new SumAggregation('sum_prices', 'blog.prices.price.gross'));

        $result = $this->blogRepository->aggregate($criteria, $context);
        $aggregation = $result->get('sum_prices');

        static::assertInstanceOf(SumResult::class, $aggregation);
        static::assertSame(25.0, $aggregation->getSum());

        $result = $this->blogRepository->aggregate($criteria, $versionContext);
        $aggregation = $result->get('sum_prices');

        static::assertInstanceOf(SumResult::class, $aggregation);
        static::assertSame(199.0, $aggregation->getSum());

        $this->blogRepository->merge($versionId, $context);

        $result = $this->blogRepository->aggregate($criteria, $context);
        $aggregation = $result->get('sum_prices');

        static::assertInstanceOf(SumResult::class, $aggregation);
        static::assertSame(199.0, $aggregation->getSum());
    }

    public function testICanAggregateManyToManyInASpecifyVersion(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $categoryId3 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $blog = [
            'id' => $blogId,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->blogRepository->create([$blog], $context);

        $versionId = $this->blogRepository->createVersion($blogId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $updated = [
            'id' => $blogId,
            'categories' => [
                ['id' => $categoryId3, 'name' => 'matching value'],
            ],
        ];
        $this->blogRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.id', $blogId));
        $criteria->addAggregation(new CountAggregation('category_count', 'blog.categories.id'));

        $result = $this->blogRepository->aggregate($criteria, $context);
        $aggregation = $result->get('category_count');

        static::assertInstanceOf(CountResult::class, $aggregation);
        static::assertSame(2, $aggregation->getCount());

        $result = $this->blogRepository->aggregate($criteria, $versionContext);
        $aggregation = $result->get('category_count');

        static::assertInstanceOf(CountResult::class, $aggregation);
        static::assertSame(3, $aggregation->getCount());

        $this->blogRepository->merge($versionId, $context);

        $result = $this->blogRepository->aggregate($criteria, $context);
        $aggregation = $result->get('category_count');

        static::assertInstanceOf(CountResult::class, $aggregation);
        static::assertSame(3, $aggregation->getCount());
    }

    public function testAggregateConsidersLiveVersionAsFallback(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'id' => $id1,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create($data, $context);

        $versionId = $this->blogRepository->createVersion($id1, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->blogRepository->update([
            [
                'id' => $id1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 900, 'net' => 900, 'linked' => false]],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('sum_price', 'blog.price.gross'));

        $aggregations = $this->blogRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(200.0, $sum->getSum());

        $aggregations = $this->blogRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());

        $this->blogRepository->merge($versionId, $context);
        $aggregations = $this->blogRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());
    }

    public function testICanAddEntitiesToSpecifyVersion(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            [
                'id' => $id1,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN-1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN-2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create($data, $context);

        $versionId = Uuid::randomHex();
        $this->blogRepository->createVersion($id1, $context, 'campaign', $versionId);
        $this->blogRepository->createVersion($id2, $context, 'campaign', $versionId);

        // check changelog written for blog 1
        $changelog = $this->getVersionData('blog', $id1, $versionId);
        static::assertCount(1, $changelog);
        static::assertSame($id1, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('blog', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        // check changelog written for blog 2 with same version
        $changelog = $this->getVersionData('blog', $id2, $versionId);
        static::assertCount(1, $changelog);
        static::assertSame($id2, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('blog', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        // update blogs of specify version
        $versionContext = $context->createWithVersionId($versionId);
        $this->blogRepository->update(
            [
                ['id' => $id1, 'ean' => 'EAN-1-update'],
                ['id' => $id2, 'ean' => 'EAN-2-update'],
            ],
            $versionContext
        );

        $blogs = $this->blogRepository->search(new Criteria([$id1, $id2]), $versionContext)->getEntities();
        // check both blogs updated
        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertCount(2, $blogs);
        static::assertTrue($blogs->has($id1));
        static::assertTrue($blogs->has($id2));
        static::assertSame('EAN-1-update', $blogs->get($id1)->getEan());
        static::assertSame('EAN-2-update', $blogs->get($id2)->getEan());

        // check existing live version not to be updated
        $blogs = $this->blogRepository->search(new Criteria([$id1, $id2]), $context)->getEntities();
        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertCount(2, $blogs);
        static::assertTrue($blogs->has($id1));
        static::assertTrue($blogs->has($id2));
        static::assertSame('EAN-1', $blogs->get($id1)->getEan());
        static::assertSame('EAN-2', $blogs->get($id2)->getEan());

        // do merge
        $this->blogRepository->merge($versionId, $context);

        // check both blogs are merged
        $blogs = $this->blogRepository->search(new Criteria([$id1, $id2]), $context)->getEntities();
        static::assertCount(2, $blogs);
        $firstBlog = $blogs->get($id1);
        static::assertNotNull($firstBlog);
        $secondBlog = $blogs->get($id2);
        static::assertNotNull($secondBlog);
        static::assertSame('EAN-1-update', $firstBlog->getEan());
        static::assertSame('EAN-2-update', $secondBlog->getEan());
    }

    public function testVersionCommitUtf8(): void
    {
        $blog = [
            'id' => Uuid::randomHex(),
            'blogNumber' => Uuid::randomHex(),
            'name' => '😄',
            'stock' => 1,
            'ean' => 'EAN-1',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $affected = static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());
        static::assertInstanceOf(EntityWrittenEvent::class, $affected->getEventByEntityName(BlogTranslationDefinition::ENTITY_NAME));
        $writtenBlogTranslations = $affected->getEventByEntityName(BlogTranslationDefinition::ENTITY_NAME)->getPayloads();

        static::assertCount(1, $writtenBlogTranslations);
        static::assertSame('😄', $writtenBlogTranslations[0]['name']);
    }

    public function testCreateOrderVersion(): void
    {
        $versionId = $this->orderRepository->createVersion($this->createOrder(), $this->context);

        static::assertTrue(Uuid::isValid($versionId));
    }

    public function testCascadeDeleteFlag(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'create', 'taxRate' => 1],
            'blogReviews' => [
                [
                    'id' => $id,
                    'userId' => $this->createUser(),
                    'channelId' => TestDefaults::CHANNEL,
                    'title' => 'Title',
                    'content' => 'Content',
                    'points' => 5,
                    'externalUser' => 'Test',
                ],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$data], Context::createDefaultContext());

        $version = Uuid::randomHex();

        static::assertSame(1, $this->getReviewCount($id, Defaults::LIVE_VERSION));

        $this->blogRepository->createVersion($id, Context::createDefaultContext(), 'test', $version);

        static::assertSame(1, $this->getReviewCount($id, Defaults::LIVE_VERSION));

        static::assertSame(0, $this->getReviewCount($id, $version));
    }

    public function testMergingIsLocked(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$data], $context);

        $versionId = $this->blogRepository->createVersion($id, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->blogRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $commits = $this->getCommits('blog', $id, $versionId);
        static::assertCount(2, $commits);

        $lockFactory = static::getContainer()->get('lock.factory');
        $lock = $lockFactory->createLock('sw-merge-version-' . $versionId);
        $lock->acquire();

        $exceptionWasThrown = false;

        try {
            $this->blogRepository->merge($versionId, $context);
        } catch (DataAbstractionLayerException) {
            $exceptionWasThrown = true;
        } finally {
            $lock->release();
        }

        static::assertTrue($exceptionWasThrown);

        // assert that commits are not removed
        $commits = $this->getCommits('blog', $id, $versionId);
        static::assertCount(2, $commits);

        // assert that changes are not applied
        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('EAN', $blog->getEan());
    }

    public function testMergeInCorrectOrder(): void
    {
        // we want to ensure that the data of a commit is persisted in the correct order
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100);

        $live = Context::createDefaultContext();

        static::getContainer()->get('blog.repository')
            ->create([$blog->build()], $live);

        // after having a simple blog - create new version
        $versionId = $this->blogRepository->createVersion($ids->get('p1'), $live);

        $version = $live->createWithVersionId($versionId);

        // now we want to create a manufacturer and update the blog record at the same time
        $update = (new BlogBuilder($ids, 'p1'))
            ->manufacturer('manufacturer');

        $blog = $update->build();
        unset($blog['type']);

        $this->blogRepository->update([$blog], $version);

        $error = null;
        $message = '';

        try {
            // when the version is merged - the manufacturer should be created first
            static::getContainer()->get('blog.repository')->merge($versionId, $live);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    private function createOrder(): string
    {
        $ruleId = Uuid::randomHex();
        $userId = $this->createUser();
        $paymentMethodId = $this->createPaymentMethod($ruleId);
        $this->addCountriesToChannel();

        $channelContext = $this->channelContextFactory->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $userId,
                ChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );
        $channelContext->setRuleIds(
            [$ruleId, $channelContext->getShippingMethod()->getAvailabilityRuleId() ?? Uuid::randomHex()]
        );

        $cart = $this->processor->process($this->createDemoCart($channelContext), $channelContext, new CartBehavior());

        return $this->orderPersister->persist($cart, $channelContext);
    }

    /**
     * @return list<string>
     */
    private function getOrderTagIds(string $orderId, Context $context): array
    {
        $order = $this->orderRepository
            ->search((new Criteria([$orderId]))->addAssociation('tags'), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($order);
        static::assertNotNull($order->getTags());

        return array_values($order->getTags()->getIds());
    }

    private function getReviewCount(string $blogId, string $versionId): int
    {
        return (int) $this->connection
            ->fetchOne(
                'SELECT COUNT(*) FROM blog_review WHERE blog_id = :id AND blog_version_id = :version',
                ['id' => Uuid::fromHexToBytes($blogId), 'version' => Uuid::fromHexToBytes($versionId)]
            );
    }

    private function createDemoCart(ChannelContext $channelContext): Cart
    {
        $cart = new Cart('a-b-c');

        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'blogNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->addTaxDataToChannel($channelContext, $blog['tax']);

        $cart->add(
            (new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE, $id, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );

        return $cart;
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->userRepository->upsert([$user], Context::createDefaultContext());

        return $userId;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getCommits(string $entity, string $id, string $versionId): array
    {
        $data = $this->connection->fetchAllAssociative(
            'SELECT d.*
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, \'$.id\') = :id
             ORDER BY auto_increment',
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );

        return array_map(static function (array $row) {
            $row['entity_id'] = json_decode((string) $row['entity_id'], true, 512, \JSON_THROW_ON_ERROR);
            $row['payload'] = json_decode((string) $row['payload'], true, 512, \JSON_THROW_ON_ERROR);

            return $row;
        }, $data);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        $data = $this->connection->fetchAllAssociative(
            'SELECT d.*
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, \'$.id\') = :id
             ORDER BY auto_increment',
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );

        return array_map(static function (array $row) {
            $row['entity_id'] = json_decode((string) $row['entity_id'], true, 512, \JSON_THROW_ON_ERROR);
            $row['payload'] = json_decode((string) $row['payload'], true, 512, \JSON_THROW_ON_ERROR);

            return $row;
        }, $data);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId, string $versionField = 'versionId'): array
    {
        $data = $this->connection->fetchAllAssociative(
            'SELECT *
             FROM version_commit_data
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, \'$.' . $foreignKeyName . '\') = :id
             AND JSON_EXTRACT(entity_id, \'$.languageId\') = :language
             AND JSON_EXTRACT(entity_id, \'$.' . $versionField . '\') = :version
             ORDER BY auto_increment',
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId,
            ]
        );

        return array_map(static function (array $row) {
            $row['entity_id'] = json_decode((string) $row['entity_id'], true, 512, \JSON_THROW_ON_ERROR);
            $row['payload'] = json_decode((string) $row['payload'], true, 512, \JSON_THROW_ON_ERROR);

            return $row;
        }, $data);
    }

    private function createPaymentMethod(string $ruleId): string
    {
        $paymentMethodId = Uuid::randomHex();
        $repository = static::getContainer()->get('payment_method.repository');

        $ruleRegistry = static::getContainer()->get(RuleConditionRegistry::class);
        $prop = new \ReflectionProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => TestPaymentHandler::class,
            'name' => 'Payment',
            'technicalName' => 'payment_test',
            'active' => true,
            'position' => 0,
            'availabilityRule' => [
                'id' => $ruleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
            'channels' => [
                [
                    'id' => TestDefaults::CHANNEL,
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $paymentMethodId;
    }
}
