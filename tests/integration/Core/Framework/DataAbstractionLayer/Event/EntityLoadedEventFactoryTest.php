<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Test\Product\ProductBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class EntityLoadedEventFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private IdsCollection $ids;

    private EntityLoadedEventFactory $entityLoadedEventFactory;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->entityLoadedEventFactory = static::getContainer()->get(EntityLoadedEventFactory::class);
        $this->ids = new IdsCollection();
    }

    public function testCreate(): void
    {
        $builder = (new ProductBuilder($this->ids, 'p1'))
            ->price(10)
            ->category('c1')
            ->manufacturer('m1')
            ->prices('r1', 5);

        $this->productRepository->create([$builder->build()], Context::createDefaultContext());

        $criteria = new Criteria([$this->ids->get('p1')]);
        $criteria->addAssociations([
            'manufacturer',
            'prices',
            'categories',
        ]);

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertNotNull($product);

        $product->addExtension('test', new LanguageCollection([
            (new LanguageEntity())->assign(['id' => $this->ids->create('l1'), '_entityName' => 'language']),
        ]));

        $events = $this->entityLoadedEventFactory->create([$product], Context::createDefaultContext())->getEvents();
        static::assertNotNull($events);
        static::assertContainsOnlyInstancesOf(EntityLoadedEvent::class, $events);
        /** @var NestedEventCollection<EntityLoadedEvent<Entity>> $events */
        $createdEvents = $events->map(static fn (EntityLoadedEvent $event): string => $event->getName());
        sort($createdEvents);

        static::assertSame([
            'category.loaded',
            'language.loaded',
            'product.loaded',
            'product_manufacturer.loaded',
            'product_price.loaded',
        ], $createdEvents);
    }

    public function testCollectionWithEntitiesMixed(): void
    {
        $tax = (new TaxEntity())->assign(['_entityName' => 'tax']);

        $events = $this->entityLoadedEventFactory->create([new ProductCollection(), $tax], Context::createDefaultContext())->getEvents();
        static::assertNotNull($events);
        static::assertContainsOnlyInstancesOf(EntityLoadedEvent::class, $events);
        /** @var NestedEventCollection<EntityLoadedEvent<Entity>> $events */
        $createdEvents = $events->map(static fn (EntityLoadedEvent $event): string => $event->getName());
        sort($createdEvents);

        static::assertSame([
            'tax.loaded',
        ], $createdEvents);
    }
}
