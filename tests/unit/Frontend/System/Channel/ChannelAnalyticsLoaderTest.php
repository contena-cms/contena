<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\System\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsCollection;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\System\Channel\ChannelAnalyticsLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ChannelAnalyticsLoader::class)]
class ChannelAnalyticsLoaderTest extends TestCase
{
    public function testChannelDoesNotHaveAnalytics(): void
    {
        $event = $this->getEvent(Generator::generateChannelContext());
        /** @var StaticEntityRepository<ChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([]);

        $loader = new ChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayNotHasKey('frontendAnalytics', $event->getParameters());
    }

    public function testChannelHasAnalytics(): void
    {
        $analyticsId = Uuid::randomHex();
        $channelContext = Generator::generateChannelContext();
        $channelContext->getChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($channelContext);
        $analytics = new ChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        /** @var StaticEntityRepository<ChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new ChannelAnalyticsCollection([$analytics])]);

        $loader = new ChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('frontendAnalytics', $event->getParameters());
        static::assertInstanceOf(ChannelAnalyticsEntity::class, $event->getParameters()['frontendAnalytics']);
    }

    public function testChannelAnalyticsNotFound(): void
    {
        $analyticsId = Uuid::randomHex();
        $channelContext = Generator::generateChannelContext();
        $channelContext->getChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($channelContext);
        /** @var StaticEntityRepository<ChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new ChannelAnalyticsCollection([])]);

        $loader = new ChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('frontendAnalytics', $event->getParameters());
        static::assertNull($event->getParameters()['frontendAnalytics']);
    }

    private function getEvent(ChannelContext $channelContext): FrontendRenderEvent
    {
        return new FrontendRenderEvent(
            'test.html.twig',
            [],
            new Request(),
            $channelContext,
        );
    }
}
