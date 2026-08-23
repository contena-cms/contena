<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
class SitemapChannelProvider
{
    private const int DEFAULT_BATCH_SIZE = 500;

    private readonly int $batchSize;

    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
        int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        $this->batchSize = max(1, $batchSize);
    }

    /**
     * @return \Generator<int, ChannelEntity>
     */
    public function getChannels(Criteria $criteria): \Generator
    {
        yield from $this->iterate($criteria, Context::createDefaultContext());

        $tenantCriteria = clone $criteria;
        $tenantCriteria->addFilter(new NotEqualsFilter('tenantId', null));

        yield from $this->iterate($tenantCriteria, Context::createGlobalContext());
    }

    /**
     * @return \Generator<int, ChannelEntity>
     */
    private function iterate(Criteria $criteria, Context $context): \Generator
    {
        $lastId = null;

        while (true) {
            $iterationCriteria = clone $criteria;
            $iterationCriteria->setLimit($this->batchSize);
            $iterationCriteria->setOffset(null);
            $iterationCriteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
            $iterationCriteria->resetSorting();
            $iterationCriteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));

            if ($lastId !== null) {
                $iterationCriteria->addFilter(new RangeFilter('id', [RangeFilter::GT => Uuid::fromHexToBytes($lastId)]));
            }

            $channels = $this->channelRepository->search($iterationCriteria, $context)->getEntities();

            foreach ($channels as $channel) {
                yield $channel;
            }

            if (\count($channels) < $this->batchSize) {
                return;
            }

            $lastChannel = $channels->last();
            if (!$lastChannel instanceof ChannelEntity) {
                return;
            }

            $lastId = $lastChannel->getId();
        }
    }
}
