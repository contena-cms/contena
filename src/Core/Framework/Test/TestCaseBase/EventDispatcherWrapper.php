<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class EventDispatcherWrapper
{
    /**
     * @param callable $callback
     */
    public function __construct(
        private $callback,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly bool $once,
        private readonly string $eventName
    ) {
    }

    public function __invoke(): void
    {
        $callback = $this->callback;

        $callback(...\func_get_args());

        if ($this->once) {
            $this->dispatcher->removeListener($this->eventName, $this);
        }
    }
}
