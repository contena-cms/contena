<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Event\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Event\CartMergedEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Storefront\Event\CartMergedSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartMergedSubscriber::class)]
class CartMergedSubscriberTest extends TestCase
{
    public function testMergedHintIsAdded(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('checkout.cart-merged-hint')
            ->willReturn('checkout.cart-merged-hint');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);

        static::assertNotCount(0, $infoFlash = $session->getFlashBag()->get('info'));
        static::assertSame('checkout.cart-merged-hint', $infoFlash[0]);
    }

    public function testMergedHintIsNotAddedWhenPreviousCartIsEmpty(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->never())->method('trans');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $context = Generator::generateSalesChannelContext(token: 'currentToken');
        $event = new CartMergedEvent(new Cart('customerToken'), $context, new Cart('currentToken'));

        $subscriber->addCartMergedNoticeFlash($event);

        static::assertCount(0, $session->getFlashBag()->get('info'));
    }

    public function testGetSubscribedEventsReturnsAddCartMergedNoticeFlash(): void
    {
        static::assertSame(
            [CartMergedEvent::class => 'addCartMergedNoticeFlash'],
            CartMergedSubscriber::getSubscribedEvents()
        );
    }

    public function testMergedSubscriberDoNothingWithoutSessionAssigned(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->never())->method('trans');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);

        static::assertCount(0, $session->getFlashBag()->get('info'));
    }

    public function testMergedSubscriberDoNothingWithEmptyRequestStack(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $requestStack = new RequestStack();

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->never())->method('trans');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);

        static::assertCount(0, $session->getFlashBag()->get('info'));
    }

    public function testMergedSubscriberDoNothingWithIncompatibleSession(): void
    {
        $session = static::createStub(SessionInterface::class);
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->never())->method('trans');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);
    }

}
