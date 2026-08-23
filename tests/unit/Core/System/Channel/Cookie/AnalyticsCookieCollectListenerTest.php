<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsCollection;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Cookie\AnalyticsCookieCollectListener;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AnalyticsCookieCollectListener::class)]
class AnalyticsCookieCollectListenerTest extends TestCase
{
    private AnalyticsCookieCollectListener $listener;

    /**
     * @var StaticEntityRepository<ChannelAnalyticsCollection>
     */
    private StaticEntityRepository $analyticsRepo;

    protected function setUp(): void
    {
        $this->analyticsRepo = new StaticEntityRepository([]);
        $this->listener = new AnalyticsCookieCollectListener($this->analyticsRepo);
    }

    public function testChannelHasNoAnalyticsId(): void
    {
        $channel = new ChannelEntity();
        $channel->setId('test-id');

        $context = Generator::generateChannelContext(channel: $channel);

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $event = new CookieGroupCollectEvent(new CookieGroupCollection([$statisticalGroup, $marketingGroup]), new Request(), $context);

        $this->listener->__invoke($event);

        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL)?->getEntries()?->get('google-analytics-enabled'));
        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->get('google-ads-enabled'));
    }

    public function testChannelNeedsToLoadAnalyticsButIsNotActive(): void
    {
        $channel = new ChannelEntity();
        $channel->setId('channel-id');
        $channel->setAnalyticsId('analytics-id');
        $context = Generator::generateChannelContext(channel: $channel);

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $event = new CookieGroupCollectEvent(new CookieGroupCollection([$statisticalGroup, $marketingGroup]), new Request(), $context);

        $analyticsEntity = $this->createChannelAnalyticsEntity(active: false);

        $this->analyticsRepo->addSearch(new ChannelAnalyticsCollection([$analyticsEntity]));

        $this->listener->__invoke($event);

        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL)?->getEntries()?->get('google-analytics-enabled'));
        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->get('google-ads-enabled'));
    }

    public function testStatisticalAndMarketingCookieGroupsNotPresent(): void
    {
        $context = $this->createChannelContext();

        $cookieGroupCollection = new CookieGroupCollection([new CookieGroup('test')]);

        $event = new CookieGroupCollectEvent($cookieGroupCollection, new Request(), $context);
        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
    }

    public function testCookiesAreAdded(): void
    {
        $context = $this->createChannelContext();

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $cookieGroupCollection = new CookieGroupCollection([$statisticalGroup, $marketingGroup]);

        $event = new CookieGroupCollectEvent($cookieGroupCollection, new Request(), $context);

        $this->listener->__invoke($event);

        $adsCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL)?->getEntries()?->get('google-analytics-enabled');
        static::assertNotNull($adsCookie);

        $adsCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->get('google-ads-enabled');
        static::assertNotNull($adsCookie);
    }

    private function createChannelAnalyticsEntity(bool $active = true): ChannelAnalyticsEntity
    {
        $analyticsEntity = new ChannelAnalyticsEntity();
        $analyticsEntity->setId('analytics-id');
        $analyticsEntity->setActive($active);

        return $analyticsEntity;
    }

    private function createChannelContext(): ChannelContext
    {
        $analyticsEntity = $this->createChannelAnalyticsEntity();

        $channel = new ChannelEntity();
        $channel->setId('channel-id');
        $channel->setAnalyticsId($analyticsEntity->getId());
        $channel->setAnalytics($analyticsEntity);

        return Generator::generateChannelContext(channel: $channel);
    }
}
