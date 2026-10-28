<?php declare(strict_types=1);

namespace Contena\Storefront\System\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelAnalyticsLoader
{
    /**
     * @param EntityRepository<SalesChannelAnalyticsCollection> $salesChannelAnalyticsRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelAnalyticsRepository,
    ) {
    }

}
