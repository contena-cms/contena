<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Account\Overview;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\UserRoute;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\Channel\OrderRoute;
use Contena\Core\Checkout\Order\Channel\OrderRouteResponse;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Frontend\Checkout\Order\OrderBlogAvailabilityResolver;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPage;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Contena\Frontend\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountOverviewPageLoader::class)]
class AccountOverviewPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&Stub $orderRoute;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = static::createStub(OrderRoute::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);
    }

    public function testLoad(): void
    {
        $order = (new OrderEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]);

        $orders = new OrderCollection([$order]);

        $orderResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $orderRoute = $this->createMock(OrderRoute::class);
        $orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $pageLoader = $this->createPageLoader(
            genericPageLoader: $genericPageLoader,
            orderRoute: $orderRoute,
            translator: $translator,
        );

        $user = new UserEntity();
        $page = $pageLoader->load(new Request(), static::createStub(ChannelContext::class), $user);

        static::assertSame($order, $page->getNewestOrder());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(AccountOverviewPageLoadedEvent::class, $events[1]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountOverviewPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->eventDispatcher,
            $this->orderRoute,
            static::createStub(UserRoute::class),
            static::createStub(NewsletterAccountPageletLoader::class),
            $this->translator,
            static::createStub(OrderBlogAvailabilityResolver::class)
        );

        $page = new AccountOverviewPage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    private function createPageLoader(
        ?GenericPageLoader $genericPageLoader = null,
        ?OrderRoute $orderRoute = null,
        ?AbstractTranslator $translator = null,
    ): AccountOverviewPageLoader {
        return new AccountOverviewPageLoader(
            $genericPageLoader ?? $this->genericPageLoader,
            $this->eventDispatcher,
            $orderRoute ?? $this->orderRoute,
            static::createStub(UserRoute::class),
            static::createStub(NewsletterAccountPageletLoader::class),
            $translator ?? $this->translator,
            static::createStub(OrderBlogAvailabilityResolver::class)
        );
    }
}

/**
 * @internal
 */
class TestAccountOverviewPageLoader extends AccountOverviewPageLoader
{
    public function setMetaInformationAccess(AccountOverviewPage $page): void
    {
        self::setMetaInformation($page);
    }
}
