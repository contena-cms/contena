<?php declare(strict_types=1);

namespace Contena\Storefront\System\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Contena\Storefront\Event\StorefrontRenderEvent;

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

    public function loadAnalytics(StorefrontRenderEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $analyticsId = $salesChannelContext->getSalesChannel()->getAnalyticsId();
        if ($analyticsId === null || $analyticsId === '') {
            return;
        }

        $criteria = new Criteria([$analyticsId]);
        $criteria->setTitle('sales-channel::load-analytics');

        $analytics = $this->salesChannelAnalyticsRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();

        $event->setParameter('storefrontAnalytics', $analytics);
    }
}
