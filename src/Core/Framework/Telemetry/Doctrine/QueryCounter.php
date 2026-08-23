<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Doctrine;

/**
 * Request-scoped holder for the number of SQL statements executed on a connection. A single instance
 * is shared across the {@see QueryCountMiddleware} driver chain and read back by the HTTP request
 * metric subscriber (which locates it via the live connection's middleware list).
 *
 * @internal
 */
final class QueryCounter
{
    private int $count = 0;

    public function increment(): void
    {
        ++$this->count;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * Returns the current count and resets it to zero, so the next request starts fresh.
     */
    public function reset(): int
    {
        $count = $this->count;
        $this->count = 0;

        return $count;
    }
}
