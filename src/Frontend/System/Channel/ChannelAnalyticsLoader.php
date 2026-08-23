<?php declare(strict_types=1);

namespace Contena\Frontend\System\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsCollection;
use Contena\Frontend\Event\FrontendRenderEvent;

/**
 * @internal
 */
class ChannelAnalyticsLoader
{
    /**
     * @param EntityRepository<ChannelAnalyticsCollection> $channelAnalyticsRepository
     */
    public function __construct(
        private readonly EntityRepository $channelAnalyticsRepository,
    ) {
    }

    public function loadAnalytics(FrontendRenderEvent $event): void
    {
        $channelContext = $event->getChannelContext();
        $analyticsId = $channelContext->getChannel()->getAnalyticsId();
        if ($analyticsId === null || $analyticsId === '') {
            return;
        }

        $criteria = new Criteria([$analyticsId]);
        $criteria->setTitle('channel::load-analytics');

        $analytics = $this->channelAnalyticsRepository->search($criteria, $channelContext->getContext())->getEntities()->first();

        $event->setParameter('frontendAnalytics', $analytics);
    }
}
