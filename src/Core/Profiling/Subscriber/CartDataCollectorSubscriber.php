<?php declare(strict_types=1);

namespace Contena\Core\Profiling\Subscriber;

use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class CartDataCollectorSubscriber extends AbstractDataCollector implements EventSubscriberInterface, ResetInterface
{
    private ?string $cartToken = null;

    private ?ChannelContext $channelContext = null;


    public static function getSubscribedEvents(): array
    {
        return [
            ChannelContextResolvedEvent::class => 'onContextResolved',
        ];
    }

    public function reset(): void
    {
        parent::reset();
        $this->cartToken = null;
        $this->channelContext = null;
    }


    public function getCurrency(): string
    {
        // Should never be null if there is a cart, however if it would be the case, the symfony toolbar yields an error, which should be prevented
        return $this->data['currency'] ?? 'EUR';
    }

    public function getItemCount(): int
    {
        return $this->getCart()?->getLineItems()->count() ?? 0;
    }

    public function getCartTotal(): float
    {
        return $this->getCart()?->getPrice()?->getTotalPrice() ?? 0.0;
    }

    /**
     * @return array<string, array{serviceId: string, priority: int, decoratedBy: list<array{serviceId: string, priority: int}>}>
     */
    public function getCollectors(): array
    {
        return $this->data['collectors'] ?? [];
    }

    /**
     * @return array<string, array{serviceId: string, priority: int, decoratedBy: list<array{serviceId: string, priority: int}>}>
     */
    public function getProcessors(): array
    {
        return $this->data['processors'] ?? [];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'cart' => $this->getCartData(),
            'currency' => $this->channelContext?->getCurrency()->getIsoCode(),
            'collectors' => $this->cartCollectors,
            'processors' => $this->cartProcessors,
        ];
    }

    public static function getTemplate(): string
    {
        return '@Profiling/Collector/cart.html.twig';
    }

    public function onContextResolved(ChannelContextResolvedEvent $event): void
    {
        $this->cartToken = $event->getUsedToken();
        $this->channelContext = $event->getChannelContext();
    }

}
