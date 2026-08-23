<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Instrumentation;

/**
 * Monotonic elapsed-time measurement for telemetry.
 *
 * Start once via {@see start()}, read {@see getElapsedMs()} as many times as needed — each read
 * reports the milliseconds elapsed since the start. Uses the monotonic clock (`hrtime`) rather than
 * a wall-clock `ClockInterface` to keep durations immune to NTP adjustments. As a result the value is not
 * freezable in tests.
 */
final readonly class ElapsedTimer
{
    private function __construct(private int|float $startedAt)
    {
    }

    public static function start(): self
    {
        return new self(hrtime(true));
    }

    public function getElapsedMs(): float
    {
        return (hrtime(true) - $this->startedAt) / 1_000_000;
    }
}
