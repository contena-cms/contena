<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\PlatformRequest;
use Contena\Frontend\Event\FrontendRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
readonly class FrontendRouteEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FrontendRenderEvent::class => ['render', -10],
        ];
    }

    public function render(FrontendRenderEvent $event): void
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
