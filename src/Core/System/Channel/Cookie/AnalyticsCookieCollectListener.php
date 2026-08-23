<?php

declare(strict_types=1);

namespace Contena\Core\System\Channel\Cookie;

use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Content\Cookie\Struct\CookieEntry;
use Contena\Core\Content\Cookie\Struct\CookieEntryCollection;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsCollection;

/**
 * @internal
 */
class AnalyticsCookieCollectListener
{
    /**
     * @param EntityRepository<ChannelAnalyticsCollection> $channelAnalyticsRepository
     */
    public function __construct(
        private readonly EntityRepository $channelAnalyticsRepository,
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $channel = $event->getChannelContext()->getChannel();

        $analyticsId = $channel->getAnalyticsId();
        if ($analyticsId === null) {
            return;
        }

        $analytics = $channel->getAnalytics();
        if ($analytics === null) {
            $criteria = new Criteria([$analyticsId]);
            $criteria->setTitle('analytics-cookie-collect-listener::load-analytics');

            $analytics = $this->channelAnalyticsRepository->search($criteria, $event->getContext())->getEntities()->get($analyticsId);
        }

        if (!$analytics?->isActive()) {
            return;
        }

        $this->handleStatisticalGroup($event->cookieGroupCollection);
        $this->handleMarketingGroup($event->cookieGroupCollection);
    }

    private function handleStatisticalGroup(CookieGroupCollection $cookieGroupCollection): void
    {
        $statisticalCookieGroup = $cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        if (!$statisticalCookieGroup) {
            return;
        }

        $entries = $statisticalCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $statisticalCookieGroup->setEntries($entries);
        }

        $entryGoogleAnalytics = new CookieEntry('google-analytics-enabled');
        $entryGoogleAnalytics->name = 'cookie.groupStatisticalGoogleAnalytics';
        $entryGoogleAnalytics->value = '1';
        $entryGoogleAnalytics->expiration = 30;

        $entries->add($entryGoogleAnalytics);
    }

    private function handleMarketingGroup(CookieGroupCollection $cookieGroupCollection): void
    {
        $marketingCookieGroup = $cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        if (!$marketingCookieGroup) {
            return;
        }

        $entries = $marketingCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $marketingCookieGroup->setEntries($entries);
        }

        $entryGoogleAds = new CookieEntry('google-ads-enabled');
        $entryGoogleAds->name = 'cookie.groupMarketingAdConsent';
        $entryGoogleAds->value = '1';
        $entryGoogleAds->expiration = 30;

        $entries->add($entryGoogleAds);
    }
}
