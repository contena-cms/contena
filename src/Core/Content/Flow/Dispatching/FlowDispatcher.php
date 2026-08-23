<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Psr\EventDispatcher\StoppableEventInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\FlowEventAware;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
readonly class FlowDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private FlowFactory $flowFactory,
        private BufferedFlowQueue $queue,
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);
        if (!$event instanceof FlowEventAware) {
            return $event;
        }

        if (($event instanceof StoppableEventInterface && $event->isPropagationStopped()) || $event->getContext()->hasState(Context::SKIP_TRIGGER_FLOW)) {
            return $event;
        }

        $this->queue->queueFlow($this->flowFactory->createBuffered($event));

        return $event;
    }

    /**
     * @param callable $listener
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void // @phpstan-ignore-line
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }
}
