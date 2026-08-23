<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Framework\Routing\FrontendRouteEventSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(FrontendRouteEventSubscriber::class)]
class FrontendRouteEventSubscriberTest extends TestCase
{
    #[TestDox('Subscribed events register FrontendRenderEvent at priority -10')]
    public function testFrontendRenderEventIsRegistered(): void
    {
        $events = FrontendRouteEventSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(FrontendRenderEvent::class, $events);
        static::assertSame(['render', -10], $events[FrontendRenderEvent::class]);
    }

    #[TestDox('render() re-dispatches the event under the route-name and per-scope prefixed names')]
    public function testRenderRedispatchesWithRouteAndScopeNames(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['frontend']);

        $event = static::createStub(FrontendRenderEvent::class);
        $event->method('getRequest')->willReturn($request);

        $dispatchedNames = [];
        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event, ?string $name = null) use (&$dispatchedNames): object {
                $dispatchedNames[] = $name;

                return $event;
            });

        new FrontendRouteEventSubscriber($dispatcher)->render($event);

        static::assertSame(['frontend.home.page.render', 'frontend.scope.render'], $dispatchedNames);
    }
}
