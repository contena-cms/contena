<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Order\Channel\OrderRoute;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Frontend\Checkout\Order\OrderBlogAvailabilityResolver;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPage;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CheckoutFinishPageLoader::class)]
class CheckoutFinishPageLoaderTest extends TestCase
{
    public function testRobotsMetaNotSetIfGiven(): void
    {
        $orderId = Uuid::randomHex();

        $page = new CheckoutFinishPage();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn($page);

        $request = new Request([
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $page = $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId))->load(
            $request,
            Generator::generateChannelContext(),
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testRobotsMetaSetIfGiven(): void
    {
        $orderId = Uuid::randomHex();

        $page = new CheckoutFinishPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn($page);

        $request = new Request([
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $page = $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId))->load(
            $request,
            Generator::generateChannelContext(),
        );
        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testCheckoutFinishPageReturned(): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId))->load(
            $request,
            Generator::generateChannelContext(),
        );
    }

    #[DataProvider('provideGuestLogoutConstellations')]
    public function testLogoutUserSetCorrectly(bool $isGuest, bool $guestLogoutSetting, bool $expected): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $context = Generator::generateChannelContext();
        $context->getUser()?->setGuest($isGuest);
        $config = [
            $context->getChannelId() => ['core.cart.logoutGuestAfterCheckout' => $guestLogoutSetting],
        ];

        $page = $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId), $config)->load($request, $context);

        static::assertSame($expected, $page->isLogoutUser());
    }

    public function testRoundingIsSetInContext(): void
    {
        $orderId = Uuid::randomHex();
        $itemRounding = new CashRoundingConfig(2, 2.0, false);
        $totalRounding = new CashRoundingConfig(4, 4.0, false);

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([
            'orderId' => $orderId,
            'changedPayment' => false,
            'paymentFailed' => false,
        ]);

        $channelContext = Generator::generateChannelContext();
        $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId, $itemRounding, $totalRounding))->load(
            $request,
            $channelContext,
        );

        static::assertSame($channelContext->getItemRounding(), $itemRounding);
        static::assertSame($channelContext->getTotalRounding(), $totalRounding);
    }

    public function testNoUserLoggedInException(): void
    {
        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $this->expectException(CartException::class);

        $this->createLoader($pageLoader, static::createStub(OrderRoute::class))->load(
            new Request(),
            static::createStub(ChannelContext::class),
        );
    }

    public function testMissingOrderIdException(): void
    {
        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $this->expectException(RoutingException::class);

        $this->createLoader($pageLoader, static::createStub(OrderRoute::class))->load(
            new Request(),
            Generator::generateChannelContext(),
        );
    }

    public function testOrderNotFoundException(): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')
            ->willReturn(new Page());

        $request = new Request([
            'orderId' => 'invalid-order-id',
        ]);

        try {
            $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId, criteriaOrderId: 'invalid-order-id'))->load(
                $request,
                Generator::generateChannelContext(),
            );
        } catch (OrderException) {
        } catch (\Exception) {
            static::fail('Not an expected Exception');
        }
    }

    public static function provideGuestLogoutConstellations(): \Generator
    {
        yield 'Guest user, setting off' => [true, false, false];
        yield 'Logged in user, setting on' => [false, true, false];
        yield 'Guest user, setting on' => [true, true, true];
    }

    public function testTheBlogAvailabilityOfTheOrderIsResolved(): void
    {
        $orderId = Uuid::randomHex();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader->method('load')->willReturn(new Page());

        $resolver = static::createMock(OrderBlogAvailabilityResolver::class);
        $resolver
            ->expects($this->once())
            ->method('addAvailability')
            ->with(static::callback(static function (array $orders) use ($orderId): bool {
                static::assertCount(1, $orders);

                $order = $orders[0];
                static::assertInstanceOf(OrderEntity::class, $order);
                static::assertSame($orderId, $order->getId());

                return true;
            }));

        $this->createLoader($pageLoader, $this->getOrderRouteWithValidOrder($orderId), [], $resolver)->load(
            new Request(['orderId' => $orderId]),
            Generator::generateChannelContext(),
        );
    }


}
