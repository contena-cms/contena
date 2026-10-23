<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Event\CartChangedEvent;
use Contena\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\System\User\Event\UserLoginEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(CacheStateSubscriber::class)]
class CacheStateSubscriberTest extends TestCase
{
    public function testNoSubscribersInMajorMode(): void
    {
        static::assertSame([], CacheStateSubscriber::getSubscribedEvents());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testLoginThrowsInMajorMode(): void
    {
        $subscriber = new CacheStateSubscriber(static::createStub(CartService::class));
        $event = $this->createMock(UserLoginEvent::class);
        $event->expects($this->never())->method('getChannelContext');

        $this->expectException(FeatureException::class);
        $subscriber->login($event);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testCartChangedThrowsInMajorMode(): void
    {
        $subscriber = new CacheStateSubscriber(static::createStub(CartService::class));
        $event = $this->createMock(CartChangedEvent::class);
        $event->expects($this->never())->method('getChannelContext');

        $this->expectException(FeatureException::class);
        $subscriber->cartChanged($event);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testSetStatesThrowsInMajorMode(): void
    {
        $service = $this->createMock(CartService::class);
        $service->expects($this->never())->method('getCart');
        $subscriber = new CacheStateSubscriber($service);

        $this->expectException(FeatureException::class);
        $subscriber->setStates(new ControllerEvent(static::createStub(HttpKernelInterface::class), static fn () => null, new Request(), HttpKernelInterface::MAIN_REQUEST));
    }
}
