<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Channel\AbstractOrderRoute;
use Contena\Core\Checkout\Order\Channel\OrderRouteResponse;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoadedEvent;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoader;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The loader is deprecated for v6.8; remove this test with it.
 *
 * @internal
 */
#[CoversClass(AccountOrderDetailPageLoader::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class AccountOrderDetailPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private AbstractOrderRoute&MockObject $orderRoute;

    private GenericPageLoaderInterface&MockObject $genericPageLoader;

    private AccountOrderDetailPageLoader $pageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(AbstractOrderRoute::class);
        $this->genericPageLoader = $this->createMock(GenericPageLoaderInterface::class);

        $this->pageLoader = new AccountOrderDetailPageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
        );
    }

    public function testLoadPutsTheOrderOnThePage(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->orderResponse(new OrderCollection([$order])));

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $detailPage = $this->pageLoader->load(new Request(['id' => $order->getId()]), Generator::generateChannelContext());

        static::assertSame($order, $detailPage->getOrder());
        static::assertSame('noindex,follow', $detailPage->getMetaInformation()?->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);
        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(AccountOrderDetailPageLoadedEvent::class, $events[1]);
    }

    public function testLoadThrowsNotFoundWhenTheOrderDoesNotExist(): void
    {
        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->orderResponse(new OrderCollection()));

        $this->genericPageLoader
            ->expects($this->never())
            ->method('load');

        $this->expectException(NotFoundHttpException::class);

        $this->pageLoader->load(new Request(['id' => Uuid::randomHex()]), Generator::generateChannelContext());
    }

    #[DataProvider('orderIdRequestProvider')]
    public function testLoadReadsTheOrderIdFromTheExpectedBag(Request $request, string $expectedOrderId): void
    {
        $order = new OrderEntity();
        $order->setId($expectedOrderId);

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::anything(),
                static::anything(),
                static::callback(static fn (Criteria $criteria) => $criteria->getIds() === [$expectedOrderId]),
            )
            ->willReturn($this->orderResponse(new OrderCollection([$order])));

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());

        $this->genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $detailPage = $this->pageLoader->load($request, Generator::generateChannelContext());

        static::assertSame($order, $detailPage->getOrder());
    }

    public function testLoadThrowsWhenTheOrderIdIsMissing(): void
    {
        $this->orderRoute
            ->expects($this->never())
            ->method('load');

        $this->genericPageLoader
            ->expects($this->never())
            ->method('load');

        $this->expectExceptionObject(RoutingException::missingRequestParameter('id'));

        $this->pageLoader->load(new Request(), Generator::generateChannelContext());
    }

    /**
     * @return iterable<string, array{Request, string}>
     */
    public static function orderIdRequestProvider(): iterable
    {
        $attributeId = Uuid::randomHex();
        $queryId = Uuid::randomHex();

        $fromAttributes = new Request();
        $fromAttributes->attributes->set('id', $attributeId);

        $fromBothBags = new Request(['id' => $queryId]);
        $fromBothBags->attributes->set('id', $attributeId);

        yield 'route attribute' => [$fromAttributes, $attributeId];
        yield 'query fallback' => [new Request(['id' => $queryId]), $queryId];
        yield 'route attribute takes precedence over query' => [$fromBothBags, $attributeId];
    }

    private function orderResponse(OrderCollection $orders): OrderRouteResponse
    {
        return new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                $orders->count(),
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        );
    }
}
