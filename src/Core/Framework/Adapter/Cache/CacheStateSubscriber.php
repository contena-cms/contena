<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Cache states will be removed, use cache keys instead
 */
class CacheStateSubscriber implements EventSubscriberInterface
{
    /**
     * @deprecated tag:v6.8.0 - Cache states will be removed, use cache hash instead
     */
    final public const STATE_LOGGED_IN = 'logged-in';

    /**
     * @deprecated tag:v6.8.0 - Cache states will be removed, use cache hash instead
     */
    final public const STATE_CART_FILLED = 'cart-filled';

    /**
     * @internal
     */
    public function __construct(private readonly CartService $cartService)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        // Sub-features can be explicitly disabled in major mode; the removed subscriber must still register no events.
        if (Feature::isActive('v6.8.0.0') || Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            return [];
        }

        return [
            KernelEvents::CONTROLLER => [
                ['setStates', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
            ],
            UserLoginEvent::class => 'login',
        ];
    }

    public function login(UserLoginEvent $event): void
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        if (Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            return;
        }

        $event->getChannelContext()->addState(self::STATE_LOGGED_IN);
    }

    public function cartChanged(CartChangedEvent $event): void
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        if (Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            return;
        }

        $event->getChannelContext()->removeState(self::STATE_CART_FILLED);

        if ($event->getCart()->getLineItems()->count() > 0) {
            $event->getChannelContext()->addState(self::STATE_CART_FILLED);
        }
    }

    public function setStates(ControllerEvent $event): void
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        if (Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT)) {
            return;
        }

        /** @var ChannelContext $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $context->removeState(self::STATE_LOGGED_IN);

        $context->removeState(self::STATE_CART_FILLED);

        if ($cart->getLineItems()->count() > 0) {
            $context->addState(self::STATE_CART_FILLED);
        }

        if ($context->getUser() !== null) {
            $context->addState(self::STATE_LOGGED_IN);
        }
    }
}
