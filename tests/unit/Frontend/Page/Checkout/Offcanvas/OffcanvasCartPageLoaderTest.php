<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Offcanvas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Shipping\Channel\ShippingMethodRoute;
use Contena\Core\Checkout\Shipping\Channel\ShippingMethodRouteResponse;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodDefinition;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Contena\Frontend\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Contena\Frontend\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(OffcanvasCartPageLoader::class)]
class OffcanvasCartPageLoaderTest extends TestCase
{
    public function testOffcanvasCartPageReturned(): void
    {
        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn(new Page());

        $this->expectNotToPerformAssertions();

        $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            static::createStub(ChannelContext::class)
        );
    }

    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new OffcanvasCartPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            static::createStub(ChannelContext::class)
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new OffcanvasCartPage();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            static::createStub(ChannelContext::class)
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testShippingMethodsAreSetToPage(): void
    {
        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethodResponse = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                ShippingMethodDefinition::ENTITY_NAME,
                2,
                $shippingMethods,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $shippingMethodRoute = static::createStub(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->method('load')
            ->willReturn($shippingMethodResponse);

        $page = $this->createLoader(shippingMethodRoute: $shippingMethodRoute)->load(
            new Request(),
            static::createStub(ChannelContext::class)
        );

        static::assertSame($shippingMethods, $page->getShippingMethods());
    }

    public function testValidationEventIsDispatched(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(OffcanvasCartPageLoadedEvent::class));

        $this->createLoader(eventDispatcher: $eventDispatcher)->load(
            new Request(),
            static::createStub(ChannelContext::class)
        );
    }

    public function testOnlyAvailableFlagIsSet(): void
    {
        $request = new Request(['onlyAvailable' => true]);
        $context = Generator::generateChannelContext();

        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->expects($this->once())
            ->method('load')
            ->with($request, $context, static::equalTo(new Criteria()));

        $loader = $this->createLoader(shippingMethodRoute: $shippingMethodRoute);
        $loader->load(new Request(), $context);
    }

    private function createLoader(
        ?EventDispatcher $eventDispatcher = null,
        ?GenericPageLoader $pageLoader = null,
        ?ShippingMethodRoute $shippingMethodRoute = null,
    ): OffcanvasCartPageLoader {
        return new OffcanvasCartPageLoader(
            $eventDispatcher ?? static::createStub(EventDispatcher::class),
            static::createStub(FrontendCartFacade::class),
            $pageLoader ?? static::createStub(GenericPageLoader::class),
            $shippingMethodRoute ?? static::createStub(ShippingMethodRoute::class),
        );
    }
}
