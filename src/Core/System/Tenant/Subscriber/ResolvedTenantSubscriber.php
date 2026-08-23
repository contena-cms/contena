<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant\Subscriber;

use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Tenant\Resolver\TenantResolverChain;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs the tenant resolver chain on every main request and exposes the
 * resolution as a request attribute for the context resolvers and the
 * login validation.
 *
 * @internal
 */
final class ResolvedTenantSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly TenantResolverChain $tenantResolverChain)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['resolveTenant', KernelListenerPriorities::KERNEL_REQUEST_EVENT_PRIORITY_TENANT_RESOLVE],
        ];
    }

    public function resolveTenant(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $resolution = $this->tenantResolverChain->resolve($event->getRequest());

        if ($resolution !== null) {
            $event->getRequest()->attributes->set(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID, $resolution);
        }
    }
}
