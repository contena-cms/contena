<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\ScheduledTask;

use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaHandler;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TenantTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class CleanupCorruptedMediaHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use TenantTestBehaviour;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private CleanupCorruptedMediaHandler $handler;

    private Context $context;

    private Context $tenantContext;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->handler = $this->getContainer()->get(CleanupCorruptedMediaHandler::class);
        $this->context = Context::createDefaultContext();
        $this->tenantContext = $this->createTenantContext($this->createTenant('Media cleanup tenant'));
        $this->ids = new IdsCollection();

        $this->mediaRepository->create([
            [
                'id' => $this->ids->create('corrupted-1'),
                'fileName' => 'corrupted-file-1.jpg',
                'fileSize' => null,
                'mediaType' => 'image/jpeg',
            ],
            [
                'id' => $this->ids->create('valid'),
                'fileName' => 'valid-file-1.jpg',
                'fileSize' => 2048,
                'mediaType' => 'image/jpeg',
            ],
            [
                'id' => $this->ids->create('corrupted-2'),
                'fileName' => 'corrupted-file-2.png',
                'fileSize' => null,
                'mediaType' => 'image/png',
            ],
            [
                'id' => $this->ids->create('in-progress'),
                'fileName' => 'in-progress-file.png',
                'fileSize' => null,
                'mediaType' => 'image/png',
            ],
            [
                'id' => $this->ids->create('cdn-media'),
                'path' => 'https://cdn.example.com/image.jpg',
                'fileSize' => null,
            ],
        ], $this->context);

        $this->mediaRepository->create([
            [
                'id' => $this->ids->create('tenant-corrupted'),
                'fileName' => 'tenant-corrupted-file.jpg',
                'fileSize' => null,
                'mediaType' => 'image/jpeg',
            ],
            [
                'id' => $this->ids->create('tenant-valid'),
                'fileName' => 'tenant-valid-file.jpg',
                'fileSize' => 2048,
                'mediaType' => 'image/jpeg',
            ],
        ], $this->tenantContext);

        $corruptedCreatedAt = new \DateTimeImmutable()
            ->sub(new \DateInterval('P31D'))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement(
            'UPDATE media SET created_at = :createdAt WHERE id IN (:ids)',
            [
                'createdAt' => $corruptedCreatedAt,
                'ids' => Uuid::fromHexToBytesList([
                    $this->ids->get('corrupted-1'),
                    $this->ids->get('corrupted-2'),
                    $this->ids->get('tenant-corrupted'),
                ]),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    public function testCleanupCorruptedMedia(): void
    {
        $this->handler->run();

        $remainingMedia = $this->mediaRepository->searchIds(new Criteria([
            $this->ids->get('corrupted-1'),
            $this->ids->get('corrupted-2'),
            $this->ids->get('valid'),
            $this->ids->get('in-progress'),
            $this->ids->get('cdn-media'),
        ]), $this->context);

        $remainingIds = $remainingMedia->getIds();
        static::assertCount(3, $remainingIds);
        static::assertContains($this->ids->get('valid'), $remainingIds);
        static::assertContains($this->ids->get('in-progress'), $remainingIds);
        static::assertContains($this->ids->get('cdn-media'), $remainingIds);

        $remainingTenantIds = $this->mediaRepository->searchIds(new Criteria([
            $this->ids->get('tenant-corrupted'),
            $this->ids->get('tenant-valid'),
        ]), $this->tenantContext)->getIds();

        static::assertSame([$this->ids->get('tenant-valid')], $remainingTenantIds);
    }
}
