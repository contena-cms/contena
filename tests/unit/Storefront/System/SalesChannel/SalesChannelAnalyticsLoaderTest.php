<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Storefront\Event\StorefrontRenderEvent;
use Contena\Storefront\System\SalesChannel\SalesChannelAnalyticsLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelAnalyticsLoader::class)]
class SalesChannelAnalyticsLoaderTest extends TestCase
{
    public function testSalesChannelDoesNotHaveAnalytics(): void
    {
        $event = $this->getEvent(Generator::generateSalesChannelContext());
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayNotHasKey('storefrontAnalytics', $event->getParameters());
    }

    public function testSalesChannelHasAnalytics(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($salesChannelContext);
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('storefrontAnalytics', $event->getParameters());
        static::assertInstanceOf(SalesChannelAnalyticsEntity::class, $event->getParameters()['storefrontAnalytics']);
    }

    public function testSalesChannelAnalyticsNotFound(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($salesChannelContext);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('storefrontAnalytics', $event->getParameters());
        static::assertNull($event->getParameters()['storefrontAnalytics']);
    }

    private function getEvent(SalesChannelContext $salesChannelContext): StorefrontRenderEvent
    {
        return new StorefrontRenderEvent(
            'test.html.twig',
            [],
            new Request(),
            $salesChannelContext,
        );
    }
}
