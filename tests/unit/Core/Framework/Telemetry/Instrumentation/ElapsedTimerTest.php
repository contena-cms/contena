<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Telemetry\Instrumentation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;

/**
 * @internal
 */
#[CoversClass(ElapsedTimer::class)]
class ElapsedTimerTest extends TestCase
{
    public function testGetElapsedMsReturnsNonNegativeDuration(): void
    {
        $timer = ElapsedTimer::start();

        static::assertGreaterThanOrEqual(0.0, $timer->getElapsedMs());
    }

    public function testElapsedIsMonotonicNonDecreasing(): void
    {
        $timer = ElapsedTimer::start();

        $first = $timer->getElapsedMs();
        $second = $timer->getElapsedMs();

        // hrtime is monotonic, so a later read can never report less elapsed time than an earlier one.
        static::assertGreaterThanOrEqual($first, $second);
    }
}
