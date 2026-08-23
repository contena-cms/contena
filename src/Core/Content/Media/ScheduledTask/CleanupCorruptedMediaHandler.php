<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\ScheduledTask;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: CleanupCorruptedMediaTask::class)]
final class CleanupCorruptedMediaHandler extends ScheduledTaskHandler
{
    private const int CORRUPTED_MEDIA_GRACE_PERIOD_DAYS = 30;

    private const int CORRUPTED_MEDIA_BATCH_SIZE = 500;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly LoggerInterface $logger,
        private readonly EntityRepository $mediaRepository,
        private readonly ClockInterface $clock,
        private readonly TenantScopeContextProvider $tenantScopeContextProvider,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $this->cleanup($context);
        }
    }

    private function cleanup(Context $context): void
    {
        $lastId = null;

        while (true) {
            $criteria = $this->buildCleanupCriteria($lastId);
            $criteria->setLimit(self::CORRUPTED_MEDIA_BATCH_SIZE);

            $ids = $this->mediaRepository->searchIds($criteria, $context)->getIds();

            if ($ids === []) {
                return;
            }

            $lastId = array_last($ids);

            $ids = array_map(static fn ($id) => ['id' => $id], $ids);
            $this->mediaRepository->delete($ids, $context);
        }
    }

    private function buildCleanupCriteria(?string $lastId = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('uploadedAt', null));
        $criteria->addFilter(new EqualsFilter('path', null));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::LT => $this->clock->now()
                ->sub(new \DateInterval('P' . self::CORRUPTED_MEDIA_GRACE_PERIOD_DAYS . 'D'))
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        if ($lastId !== null) {
            $criteria->addFilter(new RangeFilter('id', [RangeFilter::GT => Uuid::fromHexToBytes($lastId)]));
        }

        return $criteria;
    }
}
