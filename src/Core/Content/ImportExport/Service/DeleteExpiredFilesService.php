<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Service;

use Psr\Clock\ClockInterface;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class DeleteExpiredFilesService
{
    /**
     * @param EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository
     */
    public function __construct(
        private readonly EntityRepository $fileRepository,
        private readonly ClockInterface $clock
    ) {
    }

    public function countFiles(Context $context): int
    {
        $criteria = $this->buildCriteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->fileRepository->searchIds($criteria, $context)->getTotal();
    }

    public function deleteFiles(Context $context): void
    {
        $criteria = $this->buildCriteria();

        $ids = $this->fileRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        $this->fileRepository->delete($ids, $context);
    }

    private function buildCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter(
            'expireDate',
            [
                RangeFilter::LT => $this->clock->now()->modify('-30 days')->format(\DATE_ATOM),
            ]
        ));

        return $criteria;
    }
}
