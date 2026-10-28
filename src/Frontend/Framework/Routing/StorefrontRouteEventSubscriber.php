<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
readonly class StorefrontRouteEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => ['render', -10],
        ];
    }

    public function render(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            $this->dispatcher->dispatch($event, $request->attributes->get('_route') . '.render');
        }

        foreach ($request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []) as $scope) {
            $this->dispatcher->dispatch($event, $scope . '.scope.render');
        }
    }
}
