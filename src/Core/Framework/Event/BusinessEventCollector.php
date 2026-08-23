<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\FrameworkException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BusinessEventCollector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function collect(Context $context): BusinessEventCollectorResponse
    {
        $result = new BusinessEventCollectorResponse();
        foreach ($this->registry->getClasses() as $class) {
            $definition = $this->define($class);
            if ($definition !== null) {
                $result->set($definition->getName(), $definition);
            }
        }
        $event = new BusinessEventCollectorEvent($result, $context);
        $this->eventDispatcher->dispatch($event, BusinessEventCollectorEvent::NAME);

        $result = $event->getCollection();
        $result->sort(static fn (BusinessEventDefinition $a, BusinessEventDefinition $b): int => $a->getName() <=> $b->getName());

        return $result;
    }

    /**
     * @param class-string<FlowEventAware> $class
     */
    public function define(string $class): ?BusinessEventDefinition
    {
        $instance = new \ReflectionClass($class)->newInstanceWithoutConstructor();
        if (!$instance instanceof FlowEventAware) {
            throw FrameworkException::invalidEventData(\sprintf('Event %s is not a business event', $class));
        }

        $name = $instance->getName();
        if ($name === '') {
            return null;
        }

        $aware = [];
        foreach (class_implements($instance) ?: [] as $interface) {
            $reflection = new \ReflectionClass($interface);
            if ($reflection->getAttributes(IsFlowEventAware::class) !== []) {
                $aware[] = lcfirst($reflection->getShortName());
                $aware[] = $interface;
            }
        }

        return new BusinessEventDefinition($name, $class, $instance::getAvailableData()->toArray(), $aware);
    }
}
