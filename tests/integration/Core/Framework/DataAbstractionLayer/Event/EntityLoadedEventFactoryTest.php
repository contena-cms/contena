<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class EntityLoadedEventFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private IdsCollection $ids;

    private EntityLoadedEventFactory $entityLoadedEventFactory;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->entityLoadedEventFactory = static::getContainer()->get(EntityLoadedEventFactory::class);
        $this->ids = new IdsCollection();
    }

    public function testCreate(): void
    {
        $builder = (new BlogBuilder($this->ids, 'p1'))
            ->price(10)
            ->category('c1')
            ->manufacturer('m1')
            ->prices('r1', 5);

        $this->blogRepository->create([$builder->build()], Context::createDefaultContext());

        $criteria = new Criteria([$this->ids->get('p1')]);
        $criteria->addAssociations([
            'manufacturer',
            'prices',
            'categories',
        ]);

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertNotNull($blog);

        $blog->addExtension('test', new LanguageCollection([
            (new LanguageEntity())->assign(['id' => $this->ids->create('l1'), '_entityName' => 'language']),
        ]));

        $events = $this->entityLoadedEventFactory->create([$blog], Context::createDefaultContext())->getEvents();
        static::assertNotNull($events);
        static::assertContainsOnlyInstancesOf(EntityLoadedEvent::class, $events);
        /** @var NestedEventCollection<EntityLoadedEvent<Entity>> $events */
        $createdEvents = $events->map(static fn (EntityLoadedEvent $event): string => $event->getName());
        sort($createdEvents);

        static::assertSame([
            'category.loaded',
            'language.loaded',
            'blog.loaded',
            'blog_manufacturer.loaded',
            'blog_price.loaded',
        ], $createdEvents);
    }

    public function testCollectionWithEntitiesMixed(): void
    {
        $tax = (new TaxEntity())->assign(['_entityName' => 'tax']);

        $events = $this->entityLoadedEventFactory->create([new BlogCollection(), $tax], Context::createDefaultContext())->getEvents();
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
