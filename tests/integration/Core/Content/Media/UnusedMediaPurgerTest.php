<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\UnusedMediaPurger;
use Contena\Core\Content\Test\Media\MediaFixtures;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class UnusedMediaPurgerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;
    use QueueTestBehaviour;

    private const string FIXTURE_FILE = __DIR__ . '/fixtures/contena-logo.png';

    private UnusedMediaPurger $unusedMediaPurger;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepo;

    private Context $context;

    protected function setUp(): void
    {
        $mediaRepo = static::getContainer()->get('media.repository');
        static::assertInstanceOf(EntityRepository::class, $mediaRepo);

        $this->mediaRepo = $mediaRepo;
        $this->context = Context::createDefaultContext();

        $this->unusedMediaPurger = $this->createPurger(
            $this->mediaRepo,
            $this->createMock(Connection::class),
            new EventDispatcher(),
            new NativeClock()
        );
    }

    public function testDeleteNotUsedMediaWithLimit(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $png = $this->getPng();
        $pdf = $this->getPdf();

        $firstPath = $txt->getPath();
        $secondPath = $png->getPath();
        $thirdPath = $pdf->getPath();

        $this->getPublicFilesystem()->writeStream($firstPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($secondPath, \fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->writeStream($thirdPath, \fopen(self::FIXTURE_FILE, 'r'));

        $this->unusedMediaPurger->deleteNotUsedMedia(limit: 2);
        $this->runWorker();

        $result = $this->mediaRepo->search(
            new Criteria([
                $txt->getId(),
                $png->getId(),
                $pdf->getId(),
            ]),
            $this->context
        );

        static::assertNull($result->getEntities()->get($txt->getId()));
        static::assertNull($result->getEntities()->get($png->getId()));
        static::assertNull($result->getEntities()->get($pdf->getId()));

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertFalse($this->getPublicFilesystem()->has($secondPath));
        static::assertFalse($this->getPublicFilesystem()->has($thirdPath));
    }

    public function testProcessesPlatformAndTenantMediaWithTheirContexts(): void
    {
        $platformMediaId = Uuid::randomHex();
        $tenantContext = $this->createTenantContext($this->createTenant('Unused media tenant'));
        $tenantMediaId = Uuid::randomHex();
        $this->mediaRepo->create([[
            'id' => $platformMediaId,
            'fileName' => 'platform-unused',
        ]], $this->context);
        $this->mediaRepo->create([[
            'id' => $tenantMediaId,
            'fileName' => 'tenant-unused',
        ]], $tenantContext);

        static::assertNotNull($this->mediaRepo->search(new Criteria([$platformMediaId]), $this->context)->getEntities()->first());
        static::assertNotNull($this->mediaRepo->search(new Criteria([$tenantMediaId]), $tenantContext)->getEntities()->first());

        $eventContexts = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UnusedMediaSearchEvent::class,
            static function (UnusedMediaSearchEvent $event) use (&$eventContexts): void {
                $eventContexts[] = $event->getContext()->getTenantId();
            },
        );
        $purger = $this->createPurger(
            $this->mediaRepo,
            static::getContainer()->get(Connection::class),
            $eventDispatcher,
            new NativeClock(),
        );

        $unusedMedia = array_merge([], ...iterator_to_array($purger->getNotUsedMedia()));
        $unusedIds = array_map(static fn (MediaEntity $media): string => $media->getId(), $unusedMedia);

        static::assertContains($platformMediaId, $unusedIds);
        static::assertContains($tenantMediaId, $unusedIds);

        $deleted = $purger->deleteNotUsedMedia();
        $this->runWorker();

        static::assertGreaterThanOrEqual(2, $deleted);
        static::assertNull($this->mediaRepo->search(new Criteria([$platformMediaId]), $this->context)->getEntities()->first());
        static::assertNull($this->mediaRepo->search(new Criteria([$tenantMediaId]), $tenantContext)->getEntities()->first());
        static::assertContains(null, $eventContexts);
        static::assertContains($tenantContext->getTenantId(), $eventContexts);
    }

    public function testDeleteNotUsedMediaWithGracePeriodHandlesEmptyBatchFromEventListener(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $this->getPublicFilesystem()->writeStream($txt->getPath(), \fopen(self::FIXTURE_FILE, 'r'));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UnusedMediaSearchEvent::class,
            static function (UnusedMediaSearchEvent $event): void {
                $event->markAsUsed($event->getUnusedIds());
            }
        );

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $purger = $this->createPurger(
            $this->mediaRepo,
            $connection,
            $eventDispatcher,
            new NativeClock()
        );

        $deleted = $purger->deleteNotUsedMedia(gracePeriodDays: 1);
        $this->runWorker();

        static::assertSame(0, $deleted);

        $stillExisting = $this->mediaRepo
            ->search(new Criteria([$txt->getId()]), $this->context)
            ->getEntities()->get($txt->getId());
        static::assertNotNull($stillExisting);
    }

    public function testGetNotUsedMediaWithOffsetAndGracePeriodHandlesEmptyBatchFromEventListener(): void
    {
        $this->setFixtureContext($this->context);

        $txt = $this->getTxt();
        $this->getPublicFilesystem()->writeStream($txt->getPath(), \fopen(self::FIXTURE_FILE, 'r'));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UnusedMediaSearchEvent::class,
            static function (UnusedMediaSearchEvent $event): void {
                $event->markAsUsed($event->getUnusedIds());
            }
        );

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $purger = $this->createPurger(
            $this->mediaRepo,
            $connection,
            $eventDispatcher,
            new NativeClock()
        );

        $batches = iterator_to_array($purger->getNotUsedMedia(offset: 0, gracePeriodDays: 1), false);

        static::assertSame([[]], $batches);
    }

    public function testGetNotUsedMediaWithOffsetPastEndDoesNotCrash(): void
    {
        $this->setFixtureContext($this->context);

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $purger = $this->createPurger(
            $this->mediaRepo,
            $connection,
            new EventDispatcher(),
            new NativeClock()
        );

        $batches = iterator_to_array($purger->getNotUsedMedia(offset: 99999, gracePeriodDays: 1), false);

        static::assertSame([[]], $batches);
    }

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    private function createPurger(
        EntityRepository $mediaRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        NativeClock $clock,
    ): UnusedMediaPurger {
        return new UnusedMediaPurger(
            $mediaRepository,
            $connection,
            $eventDispatcher,
            $clock,
            static::getContainer()->get(TenantScopeContextProvider::class),
        );
    }
}
