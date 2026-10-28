<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing;

use Contena\Core\Framework\Log\Package;
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

}
